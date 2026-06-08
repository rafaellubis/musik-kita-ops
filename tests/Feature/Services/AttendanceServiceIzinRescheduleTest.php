<?php

namespace Tests\Feature\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AttendanceServiceIzinRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;
    private Teacher $teacher;
    private Room $room;
    private Package $package;
    private Student $student;
    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(AttendanceService::class);

        $this->teacher   = Teacher::factory()->create(['is_active' => true]);
        $this->room      = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $this->package   = Package::factory()->create([
            'class_type'       => 'REGULER',
            'duration_min'     => 30,
            'price_per_month'  => 370000,
            'is_active'        => true,
        ]);
        $this->student   = Student::factory()->create(['status' => 'Aktif']);
        $this->enrollment = Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'package_id' => $this->package->id,
            'status'     => 'ACTIVE',
        ]);
    }

    public function test_schedule_replacement_mengubah_status_menjadi_izin_reschedule(): void
    {
        // Sesi dengan schedule_id (berasal dari jadwal mingguan)
        $sesi = ClassSession::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id'    => $this->student->id,
            'teacher_id'    => $this->teacher->id,
            'room_id'       => $this->room->id,
            'session_date'  => '2026-07-07',
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'status'        => 'SCHEDULED',
        ]);

        $replacement = $this->service->scheduleReplacement(
            $sesi,
            '2026-07-14',
            '15:00',
            $this->room->id,
        );

        // Sesi asli berubah jadi IZIN_RESCHEDULE
        $sesi->refresh();
        $this->assertEquals('IZIN_RESCHEDULE', $sesi->status);
        $this->assertNull($sesi->honor_code);
        $this->assertEquals(0, $sesi->honor_amount);

        // Sesi pengganti terbuat
        $this->assertNotNull($replacement);
        $this->assertEquals('SCHEDULED', $replacement->status);
        $this->assertEquals('2026-07-14', $replacement->session_date);
        $this->assertEquals('15:00:00', $replacement->start_time);
        $this->assertEquals($sesi->id, $replacement->origin_session_id);
        $this->assertNull($replacement->schedule_id);
    }

    public function test_schedule_replacement_gagal_jika_sudah_ada_pengganti(): void
    {
        $sesi = ClassSession::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id'    => $this->student->id,
            'teacher_id'    => $this->teacher->id,
            'room_id'       => $this->room->id,
            'session_date'  => '2026-07-07',
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'status'        => 'SCHEDULED',
        ]);

        // Buat replacement dulu
        $this->service->scheduleReplacement(
            $sesi,
            '2026-07-14',
            '15:00',
            $this->room->id,
        );

        // Coba schedule replacement lagi — harus gagal
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sudah memiliki sesi pengganti/i');

        $this->service->scheduleReplacement(
            $sesi,
            '2026-07-15',
            '15:00',
            $this->room->id,
        );
    }

    public function test_schedule_replacement_gagal_jika_konflik_guru(): void
    {
        $sesi = ClassSession::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id'    => $this->student->id,
            'teacher_id'    => $this->teacher->id,
            'room_id'       => $this->room->id,
            'session_date'  => '2026-07-07',
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'status'        => 'SCHEDULED',
        ]);

        // Guru sudah ada sesi lain di slot yang sama di tanggal pengganti
        ClassSession::factory()->create([
            'enrollment_id' => $this->enrollment->id,
            'student_id'    => $this->student->id,
            'teacher_id'    => $this->teacher->id,
            'room_id'       => $this->room->id,
            'session_date'  => '2026-07-14',
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'status'        => 'SCHEDULED',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sudah ada sesi lain/i');

        $this->service->scheduleReplacement(
            $sesi,
            '2026-07-14',
            '15:00',
            $this->room->id,
        );

        // Sesi asli harus tetap SCHEDULED (transaction rollback)
        $this->assertEquals('SCHEDULED', $sesi->fresh()->status);
    }
}
