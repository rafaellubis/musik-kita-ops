<?php

namespace Tests\Unit;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentImportConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_validateRow_tambah_conflict_warning_jika_guru_sudah_terjadwal(): void
    {
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $package = Package::factory()->create([
            'is_active'    => true,
            'duration_min' => 30,
            'class_type'   => 'REGULER',
        ]);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 1]);

        // Jadwal existing: guru ini sudah mengajar Senin 15:00
        $existingStudent    = Student::factory()->create(['status' => 'Aktif']);
        $existingEnrollment = Enrollment::factory()->create([
            'student_id' => $existingStudent->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $existingEnrollment->id,
            'day_of_week'   => 1, // Senin
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room->id,
            'is_active'     => true,
        ]);

        $service = app(StudentImportService::class);
        $row = [
            'full_name'      => 'Murid Baru',
            'gender'         => 'L',
            'status'         => 'Aktif',
            'package_code'   => $package->code,
            'teacher_code'   => $teacher->code,
            'preferred_day'  => 'Senin',
            'preferred_time' => '15:00',
            'kode_ruangan'   => null,
        ];

        $result = $service->validateRow(
            rowNum:             2,
            row:                $row,
            packageCodes:       [$package->code => $package->id],
            teacherCodes:       [$teacher->code => $teacher->id],
            packageDurationMap: [$package->code => 30],
        );

        // Harus return array (valid), bukan string error — konflik tidak memblok import
        $this->assertIsArray($result, 'Konflik jadwal tidak boleh memblock import — hanya warning');
        // Harus ada conflict warning
        $this->assertNotNull($result['_conflict_warning']);
        $this->assertStringContainsString('Senin', $result['_conflict_warning']);
    }

    public function test_validateRow_tidak_ada_conflict_warning_jika_tidak_ada_jadwal_bentrok(): void
    {
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $package = Package::factory()->create(['is_active' => true, 'duration_min' => 30, 'class_type' => 'REGULER']);

        $service = app(StudentImportService::class);
        $row = [
            'full_name'      => 'Murid Baru',
            'gender'         => 'L',
            'status'         => 'Aktif',
            'package_code'   => $package->code,
            'teacher_code'   => $teacher->code,
            'preferred_day'  => 'Senin',
            'preferred_time' => '15:00',
            'kode_ruangan'   => null,
        ];

        $result = $service->validateRow(
            rowNum:             2,
            row:                $row,
            packageCodes:       [$package->code => $package->id],
            teacherCodes:       [$teacher->code => $teacher->id],
            packageDurationMap: [$package->code => 30],
        );

        $this->assertIsArray($result);
        $this->assertNull($result['_conflict_warning']);
    }
}
