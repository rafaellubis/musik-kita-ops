<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Holiday;
use App\Services\EnrollmentPromotionService;
use App\Services\SessionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesPromotionClass;
use Tests\TestCase;

class EnrollmentPromotionSessionsTest extends TestCase
{
    use RefreshDatabase, CreatesPromotionClass;

    public function test_future_sessions_are_replaced_but_old_session_and_enrollment_stay_intact(): void
    {
        extract($this->promotionClass());
        $old = ClassSession::factory()->create(['enrollment_id' => $source->id, 'student_id' => $student->id, 'teacher_id' => $source->teacher_id, 'session_date' => '2026-10-05', 'status' => 'HADIR', 'honor_amount' => 42500, 'notes' => 'Latihan tangga nada.']);
        $generator = app(SessionGeneratorService::class);
        $generator->generateForMonth(2026, 11);
        $generator->generateForMonth(2026, 12);
        $futureIds = $source->classSessions()->where('session_date', '>=', '2026-11-01')->pluck('id')->all();
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $this->assertSame(0, ClassSession::whereIn('id', $futureIds)->count());
        $new = $promotion->successor->classSessions()->orderBy('session_date')->get();
        $this->assertSame(['2026-11-03', '2026-11-10', '2026-11-17', '2026-11-24', '2026-12-01', '2026-12-08', '2026-12-15', '2026-12-22'], $new->pluck('session_date')->all());
        $this->assertSame([$teacher->id], $new->pluck('teacher_id')->unique()->all());
        $this->assertSame($package->id, $source->fresh()->package_id);
        $this->assertSame('Latihan tangga nada.', $old->fresh()->notes);
        $this->assertSame(42500, $old->fresh()->honor_amount);
        $generator->generateForMonth(2026, 11);
        $this->assertSame(8, $promotion->successor->classSessions()->count());
    }

    public function test_noted_future_session_blocks_without_deleting_anything(): void
    {
        extract($this->promotionClass());
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $session = $source->classSessions()->firstOrFail();
        $session->update(['notes' => 'Catatan penting guru']);
        try {
            app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
            $this->fail('Sesi berisi catatan tidak boleh dihapus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sessions', $e->errors());
        }
        $this->assertSame(4, $source->classSessions()->count());
        $this->assertSame('Catatan penting guru', $session->fresh()->notes);
        $this->assertNull($source->fresh()->end_date);
        $this->assertSame(1, Enrollment::where('student_id', $student->id)->count());
    }
    public function test_holiday_replacement_is_rebuilt_but_previous_month_rapel_survives(): void
    {
        extract($this->promotionClass());
        Holiday::create(['date' => '2026-11-02', 'name' => 'Libur nasional', 'type' => 'Nasional', 'is_active' => true, 'is_honor_paid' => true, 'replacement_date' => '2026-11-30']);
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $origin = ClassSession::factory()->create(['enrollment_id' => $source->id, 'student_id' => $student->id, 'session_date' => '2026-10-19', 'status' => 'IZIN_RESCHEDULE']);
        $rapel = ClassSession::factory()->create(['enrollment_id' => $source->id, 'student_id' => $student->id, 'session_date' => '2026-11-06', 'start_time' => '09:00', 'end_time' => '09:30', 'origin_session_id' => $origin->id, 'attribution_year' => 2026, 'attribution_month' => 10]);
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $this->assertSame($source->id, $rapel->fresh()->enrollment_id);
        $this->assertSame([$rapel->id], $source->classSessions()->where('session_date', '>=', '2026-11-01')->pluck('id')->all());
        $this->assertSame(4, $promotion->successor->classSessions()->count());
    }

    public function test_concrete_conflict_at_holiday_replacement_rolls_back_whole_cutover(): void
    {
        extract($this->promotionClass());
        Holiday::create(['date' => '2026-11-03', 'name' => 'Libur', 'type' => 'Nasional', 'is_active' => true, 'is_honor_paid' => true, 'replacement_date' => '2026-11-30']);
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $before = $source->classSessions()->pluck('id')->all();
        ClassSession::factory()->create(['teacher_id' => $teacher->id, 'session_date' => '2026-11-30', 'start_time' => '11:00', 'end_time' => '11:30', 'status' => 'SCHEDULED']);
        try {
            app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
            $this->fail('Konflik replacement harus membatalkan transaksi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sessions', $e->errors());
        }
        $this->assertSame($before, $source->classSessions()->pluck('id')->all());
        $this->assertNull($source->fresh()->end_date);
        $this->assertSame(0, \App\Models\EnrollmentPromotion::count());
    }

    public function test_manual_session_and_leave_crossing_effective_date_are_blocked(): void
    {
        extract($this->promotionClass());
        $manual = ClassSession::factory()->create(['enrollment_id' => $source->id, 'session_date' => '2026-11-05', 'session_type' => 'MANUAL', 'status' => 'SCHEDULED']);
        try {
            app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
            $this->fail('Sesi manual tidak boleh dihapus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sessions', $e->errors());
        }
        $this->assertNotNull($manual->fresh());
        $manual->delete();
        $source->leaves()->create(['cuti_from' => '2026-10-25', 'cuti_until' => '2026-11-10', 'extension_count' => 0, 'reason' => 'Liburan keluarga']);
        try {
            app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
            $this->fail('Cuti melintasi kenaikan harus diperiksa.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('enrollment_id', $e->errors());
        }
        $this->assertNull($source->fresh()->end_date);
    }
    public function test_adjacent_slots_are_not_overlapping(): void
    {
        extract($this->promotionClass());
        $other = Enrollment::factory()->create(['teacher_id' => $teacher->id, 'status' => 'ACTIVE', 'effective_date' => '2026-01-01']);
        \App\Models\Schedule::factory()->for($other)->create(['day_of_week' => 2, 'start_time' => '10:30:00', 'end_time' => '11:00:00', 'room_id' => $room->id, 'is_active' => true]);
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $this->assertSame('11:00:00', $promotion->successor->classSessions()->firstOrFail()->start_time);
    }
    public function test_other_class_cannot_take_students_pending_successor_slot(): void
    {
        extract($this->promotionClass());
        app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $otherTeacher = \App\Models\Teacher::factory()->create(['is_active' => true]);
        $otherTeacher->instruments()->attach($source->package->instrument_id);
        $otherRoom = \App\Models\Room::factory()->create(['is_active' => true, 'supported_instruments' => [$source->package->instrument->name]]);
        $other = Enrollment::factory()->create(['student_id' => $student->id, 'teacher_id' => $otherTeacher->id, 'package_id' => $source->package_id, 'status' => 'ACTIVE', 'effective_date' => '2026-01-01']);
        $payload = ['enrollment_id' => $other->id, 'day_of_week' => 2, 'start_time' => '11:00', 'end_time' => '11:30', 'room_id' => $otherRoom->id];
        $this->actingAs($actor)->post(route('schedules.store', $student), $payload)->assertSessionHas('error');
        $this->assertSame(0, $other->schedules()->count());
        $existing = $other->schedules()->create(['day_of_week' => 3, 'start_time' => '13:00:00', 'end_time' => '13:30:00', 'room_id' => $otherRoom->id, 'is_active' => true]);
        $this->patch(route('schedules.update', [$student, $existing]), $payload)->assertSessionHas('error');
        $this->assertSame(3, $existing->fresh()->day_of_week);
        $existing->update(['is_active' => false, 'day_of_week' => 2, 'start_time' => '11:00:00', 'end_time' => '11:30:00']);
        $this->post(route('schedules.toggle-active', [$student, $existing]))->assertSessionHas('error');
        $this->assertFalse($existing->fresh()->is_active);
    }
    public function test_old_period_reschedule_remains_available_but_pending_successor_is_locked(): void
    {
        extract($this->promotionClass());
        $original = \App\Models\ClassSession::factory()->for($source)->create(['student_id' => $student->id, 'teacher_id' => $source->teacher_id, 'session_date' => '2026-10-19', 'attribution_year' => 2026, 'attribution_month' => 10, 'status' => 'IZIN_RESCHEDULE']);
        $promotion = app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $reschedule = app(\App\Services\RescheduleService::class);
        $replacement = $reschedule->createReplacement($original, '2026-11-05', '15:00', $room->id);
        $this->assertSame($source->id, $replacement->enrollment_id);
        $this->assertSame(10, $replacement->attributionMonth());
        $future = $promotion->successor->classSessions()->firstOrFail();
        try {
            $reschedule->createReplacement($future, '2026-11-06', '15:00', $room->id);
            $this->fail('Kelas penerus belum boleh diubah sebelum tanggal berlaku.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('enrollment_id', $e->errors());
        }
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        app(EnrollmentPromotionService::class)->applyDue();
        $split = $reschedule->createSplitPart($original, '2026-11-12', '15:00', $room->id, 1);
        $this->assertSame($source->id, $split->enrollment_id);
        $this->assertSame(10, $split->attributionMonth());
        $this->assertEquals(340000, $split->enrollment->package->price_per_month);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('protectedFutureSessions')]
    public function test_future_attendance_or_paid_honor_prevents_replacement(string $case): void
    {
        extract($this->promotionClass());
        app(SessionGeneratorService::class)->generateForMonth(2026, 11);
        $before = $source->classSessions()->pluck('id')->all();
        if ($case === 'attendance') {
            $source->classSessions()->firstOrFail()->update(['status' => 'HADIR', 'honor_amount' => 42500]);
        } else {
            \App\Models\HonorSlip::factory()->create(['teacher_id' => $source->teacher_id, 'month' => 11, 'year' => 2026, 'status' => 'PAID', 'paid_at' => now(), 'paid_by' => $actor->id]);
        }
        try {
            app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
            $this->fail('Sesi operasional tidak boleh diganti.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sessions', $e->errors());
        }
        $this->assertSame($before, $source->classSessions()->pluck('id')->all());
        $this->assertNull($source->fresh()->end_date);
    }

    public static function protectedFutureSessions(): array
    {
        return [['attendance'], ['paid_honor']];
    }

    public function test_reserved_slot_and_duplicate_submission_cannot_create_another_successor(): void
    {
        extract($this->promotionClass());
        app(EnrollmentPromotionService::class)->schedule($source, $data, $actor);
        $otherStudent = \App\Models\Student::factory()->create(['status' => 'Aktif']);
        $other = Enrollment::factory()->for($otherStudent)->create(['package_id' => $package->id, 'status' => 'ACTIVE', 'effective_date' => '2026-01-01']);
        foreach ([$source, $other] as $candidate) {
            try {
                app(EnrollmentPromotionService::class)->schedule($candidate, $data, $actor);
                $this->fail('Pengajuan duplikat atau slot terpakai harus ditolak.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($candidate->id === $source->id ? 'enrollment_id' : 'start_time', $e->errors());
            }
        }
        $this->assertSame(1, \App\Models\EnrollmentPromotion::count());
        $this->assertNull($other->fresh()->end_date);
    }
}
