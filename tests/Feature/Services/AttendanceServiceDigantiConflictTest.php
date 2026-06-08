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

class AttendanceServiceDigantiConflictTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(Teacher $teacher, Room $room, string $date, string $start, string $end, string $status = 'SCHEDULED'): ClassSession
    {
        $package    = Package::factory()->create(['class_type' => 'REGULER', 'duration_min' => 30, 'price_per_month' => 370000, 'is_active' => true]);
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        return ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'session_date'  => $date,
            'start_time'    => $start,
            'end_time'      => $end,
            'status'        => $status,
        ]);
    }

    public function test_diganti_diblokir_jika_guru_pengganti_sudah_ada_sesi_bersamaan(): void
    {
        $guruAsli     = Teacher::factory()->create(['is_active' => true]);
        $guruPengganti = Teacher::factory()->create(['is_active' => true]);
        $room         = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

        $sesiTarget = $this->makeSession($guruAsli, $room, '2026-07-01', '15:00:00', '15:30:00', 'SCHEDULED');

        // Guru pengganti sudah punya sesi lain di jam yang sama
        $this->makeSession($guruPengganti, $room, '2026-07-01', '15:00:00', '15:30:00', 'SCHEDULED');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sudah memiliki sesi lain/i');

        app(AttendanceService::class)->recordAttendance($sesiTarget, [
            'status'                => 'DIGANTI',
            'substitute_teacher_id' => $guruPengganti->id,
            '__session'             => $sesiTarget,
        ]);
    }

    public function test_diganti_diblokir_jika_ruangan_pengganti_sudah_dipakai(): void
    {
        $guruAsli     = Teacher::factory()->create(['is_active' => true]);
        $guruPengganti = Teacher::factory()->create(['is_active' => true]);
        $roomAsli     = Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $roomPengganti = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

        $sesiTarget = $this->makeSession($guruAsli, $roomAsli, '2026-07-01', '15:00:00', '15:30:00', 'SCHEDULED');

        // Ruangan pengganti sudah dipakai di jam yang sama
        $package2    = Package::factory()->create(['class_type' => 'REGULER', 'duration_min' => 30, 'price_per_month' => 370000, 'is_active' => true]);
        $student2    = Student::factory()->create(['status' => 'Aktif']);
        $guruLain    = Teacher::factory()->create(['is_active' => true]);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id,
            'teacher_id' => $guruLain->id,
            'package_id' => $package2->id,
            'status'     => 'ACTIVE',
        ]);
        ClassSession::factory()->create([
            'enrollment_id' => $enrollment2->id,
            'student_id'    => $student2->id,
            'teacher_id'    => $guruLain->id,
            'room_id'       => $roomPengganti->id,
            'session_date'  => '2026-07-01',
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'status'        => 'SCHEDULED',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ruangan pengganti/i');

        app(AttendanceService::class)->recordAttendance($sesiTarget, [
            'status'                => 'DIGANTI',
            'substitute_teacher_id' => $guruPengganti->id,
            'substitute_room_id'    => $roomPengganti->id,
            '__session'             => $sesiTarget,
        ]);
    }

    public function test_diganti_berhasil_jika_guru_dan_ruang_pengganti_bebas(): void
    {
        $guruAsli     = Teacher::factory()->create(['is_active' => true]);
        $guruPengganti = Teacher::factory()->create(['is_active' => true]);
        $room         = Room::factory()->create(['capacity' => 1, 'is_active' => true]);

        $sesiTarget = $this->makeSession($guruAsli, $room, '2026-07-01', '15:00:00', '15:30:00', 'SCHEDULED');

        // Tidak ada konflik — harus berhasil
        $result = app(AttendanceService::class)->recordAttendance($sesiTarget, [
            'status'                => 'DIGANTI',
            'substitute_teacher_id' => $guruPengganti->id,
            '__session'             => $sesiTarget,
        ]);

        $this->assertEquals('DIGANTI', $result->status);
        $this->assertEquals($guruPengganti->id, $result->substitute_teacher_id);
    }
}
