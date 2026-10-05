<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Student;
use Carbon\Carbon;
use Database\Seeders\InstrumentSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\TeacherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_students_use_primary_enrollments_and_reruns_without_duplicates(): void
    {
        $this->travelTo(Carbon::parse('2026-06-20 10:00:00'));
        $this->seed([
            InstrumentSeeder::class,
            PackageSeeder::class,
            TeacherSeeder::class,
        ]);

        $this->seed(StudentSeeder::class);
        $this->seed(StudentSeeder::class);

        $this->assertSame(5, Student::count());
        $this->assertSame(4, Enrollment::count());

        foreach ([
            ['M-2026-0001', 'PIANO_REG_L1', 'T02', '2025-09-01', 'ACTIVE'],
            ['M-2026-0002', 'GITAR_HOBBY_30', 'T06', '2026-06-22', 'TRIAL'],
            ['M-2026-0003', 'KIDS_GRUP_6BULAN', 'T16', '2026-01-15', 'ACTIVE'],
            ['M-2026-0005', 'VOCAL_REG_BASIC', 'T10', '2024-08-01', 'ON_LEAVE'],
        ] as [$studentCode, $packageCode, $teacherCode, $effectiveDate, $status]) {
            $student = Student::where('student_code', $studentCode)->sole();
            $enrollment = $student->enrollments()->with(['package', 'teacher'])->sole();

            $this->assertSame($enrollment->id, $student->primary_enrollment_id);
            $this->assertSame(1, (int) $enrollment->is_primary);
            $this->assertSame($packageCode, $enrollment->package->code);
            $this->assertSame($teacherCode, $enrollment->teacher->code);
            $this->assertSame($effectiveDate, $enrollment->effective_date->toDateString());
            $this->assertSame($status, $enrollment->status);
        }

        $calon = Student::where('student_code', 'M-2026-0004')->sole();
        $this->assertNull($calon->primary_enrollment_id);
        $this->assertSame(0, $calon->enrollments()->count());
    }
}
