<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\ScheduleReminderLog;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\WhatsappMessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduleReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        WhatsappMessageTemplate::create([
            'code'       => WhatsappMessageTemplate::CODE_SCHEDULE_REMINDER,
            'name'       => 'Pengingat Jadwal',
            'body'       => 'Halo {nama_ortu}, jadwal {nama_murid} {tanggal}: {daftar_jadwal}',
            'is_active'  => true,
            'sort_order' => 2,
        ]);

        config([
            'schedule_reminder.enabled'    => true,
            'schedule_reminder.mode'       => 'day_before',
            'schedule_reminder.send_time'  => '18:00',
            'services.fonnte.token'        => 'test-fonnte-token',
            'services.fonnte.base_url'     => 'https://api.fonnte.com',
            'services.fonnte.country_code' => '62',
        ]);
    }

    private function fakeFonnte(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id'     => ['80367170'],
                'detail' => 'success! message in queue',
            ], 200),
        ]);
    }

    /** @return array{student: Student, session: ClassSession} */
    private function studentWithScheduledSession(
        string $sessionDate,
        ?string $parentPhone = '0816920592',
        ?string $studentPhone = null,
    ): array {
        $instrument = Instrument::factory()->create(['name' => 'Piano']);
        $package = Package::factory()->create(['instrument_id' => $instrument->id]);
        $teacher = Teacher::factory()->create(['name' => 'Pak Budi']);
        $room = Room::factory()->create(['name' => 'Ruang A']);

        $student = Student::factory()->create([
            'status'       => 'Aktif',
            'parent_name'  => 'Budi Ortu',
            'parent_phone' => $parentPhone,
            'phone'        => $studentPhone,
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id'  => $student->id,
            'package_id'  => $package->id,
            'teacher_id'  => $teacher->id,
            'status'      => Enrollment::STATUS_ACTIVE,
            'is_primary'  => true,
        ]);

        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $session = ClassSession::factory()->create([
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'session_date'  => $sessionDate,
            'start_time'    => '16:00:00',
            'end_time'      => '17:00:00',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        return ['student' => $student, 'session' => $session];
    }

    public function test_sends_reminder_for_tomorrow_session_in_day_before_mode(): void
    {
        $this->fakeFonnte();

        $tomorrow = now()->addDay()->toDateString();
        $this->studentWithScheduledSession($tomorrow);

        $this->travelTo(now()->setTime(18, 0));

        $this->artisan('schedule-reminders:send')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request['target'] === '0816920592'
                && str_contains($request['message'], 'Budi Ortu');
        });

        $this->assertDatabaseCount('schedule_reminder_logs', 1);
        $this->assertDatabaseHas('schedule_reminder_logs', [
            'reminder_mode' => ScheduleReminderLog::MODE_DAY_BEFORE,
            'status'        => ScheduleReminderLog::STATUS_SUCCESS,
        ]);
    }

    public function test_does_not_send_duplicate_reminder(): void
    {
        $this->fakeFonnte();

        $tomorrow = now()->addDay()->toDateString();
        ['student' => $student, 'session' => $session] = $this->studentWithScheduledSession($tomorrow);

        ScheduleReminderLog::create([
            'student_id'             => $student->id,
            'target_date'            => $tomorrow,
            'reminder_mode'          => ScheduleReminderLog::MODE_DAY_BEFORE,
            'class_session_ids'      => [$session->id],
            'provider'               => 'fonnte',
            'phone'                  => '62816920592',
            'message_body'           => 'sudah terkirim',
            'provider_message_ids'   => ['1'],
            'status'                 => ScheduleReminderLog::STATUS_SUCCESS,
            'sent_at'                => now(),
        ]);

        $this->travelTo(now()->setTime(18, 0));

        $this->artisan('schedule-reminders:send')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('schedule_reminder_logs', 1);
    }

    public function test_skips_non_scheduled_sessions(): void
    {
        $this->fakeFonnte();

        $tomorrow = now()->addDay()->toDateString();
        ['student' => $student, 'session' => $session] = $this->studentWithScheduledSession($tomorrow);
        $session->update(['status' => ClassSession::STATUS_LIBUR]);

        $this->travelTo(now()->setTime(18, 0));

        $this->artisan('schedule-reminders:send')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('schedule_reminder_logs', 0);
    }

    public function test_skips_when_not_send_time(): void
    {
        $this->fakeFonnte();

        $tomorrow = now()->addDay()->toDateString();
        $this->studentWithScheduledSession($tomorrow);

        $this->travelTo(now()->setTime(10, 0));

        $this->artisan('schedule-reminders:send')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_dry_run_does_not_send(): void
    {
        Http::fake();

        $tomorrow = now()->addDay()->toDateString();
        $this->studentWithScheduledSession($tomorrow);

        $this->artisan('schedule-reminders:send --dry-run')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('schedule_reminder_logs', 0);
    }

    public function test_hours_before_mode_sends_within_window(): void
    {
        $this->fakeFonnte();

        config(['schedule_reminder.mode' => 'hours_before', 'schedule_reminder.hours_before' => 2]);

        $today = now()->toDateString();
        $this->studentWithScheduledSession($today);

        // Sesi 16:00, run jam 14:00 → 2 jam sebelum
        $this->travelTo(now()->setTime(14, 0));

        $this->artisan('schedule-reminders:send')
            ->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertDatabaseHas('schedule_reminder_logs', [
            'reminder_mode' => ScheduleReminderLog::MODE_HOURS_BEFORE,
            'status'        => ScheduleReminderLog::STATUS_SUCCESS,
        ]);
    }

    public function test_hours_before_mode_skips_outside_window(): void
    {
        Http::fake();

        config(['schedule_reminder.mode' => 'hours_before', 'schedule_reminder.hours_before' => 2]);

        $today = now()->toDateString();
        $this->studentWithScheduledSession($today);

        // Sesi 16:00, run jam 13:45 → belum masuk window 2 jam (±7 menit)
        $this->travelTo(now()->setTime(13, 45));

        $this->artisan('schedule-reminders:send')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_logs_failure_on_invalid_token(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => false,
                'reason' => 'token invalid',
            ], 200),
        ]);

        $tomorrow = now()->addDay()->toDateString();
        $this->studentWithScheduledSession($tomorrow);

        $this->travelTo(now()->setTime(18, 0));

        $this->artisan('schedule-reminders:send')
            ->assertFailed();

        $this->assertDatabaseHas('schedule_reminder_logs', [
            'status' => ScheduleReminderLog::STATUS_FAILED,
        ]);
    }

    public function test_falls_back_to_student_phone_when_parent_phone_null(): void
    {
        $this->fakeFonnte();

        $tomorrow = now()->addDay()->toDateString();
        $this->studentWithScheduledSession($tomorrow, null, '081234567890');

        $this->travelTo(now()->setTime(18, 0));

        $this->artisan('schedule-reminders:send')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request['target'] === '081234567890';
        });

        $this->assertDatabaseHas('schedule_reminder_logs', [
            'phone'  => '6281234567890',
            'status' => ScheduleReminderLog::STATUS_SUCCESS,
        ]);
    }

    public function test_skips_when_both_phones_invalid(): void
    {
        Http::fake();

        $tomorrow = now()->addDay()->toDateString();
        $this->studentWithScheduledSession($tomorrow, null, null);

        $this->travelTo(now()->setTime(18, 0));

        $this->artisan('schedule-reminders:send')
            ->assertSuccessful()
            ->expectsOutputToContain('dilewati: 1');

        Http::assertNothingSent();
        $this->assertDatabaseCount('schedule_reminder_logs', 0);
    }
}
