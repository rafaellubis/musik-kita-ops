<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
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

    public function test_rerunning_seeder_preserves_existing_lifecycle_enrollments_and_primary_choice(): void
    {
        $this->travelTo(Carbon::parse('2026-06-20 10:00:00'));
        $this->seed([
            InstrumentSeeder::class,
            PackageSeeder::class,
            TeacherSeeder::class,
        ]);
        $this->seed(StudentSeeder::class);

        $andi = Student::where('student_code', 'M-2026-0001')->sole();
        $sari = Student::where('student_code', 'M-2026-0002')->sole();
        $eka = Student::where('student_code', 'M-2026-0005')->sole();
        $sariEnrollment = $sari->enrollments()->sole();
        $teacherDebora = Teacher::where('code', 'T03')->sole();

        $andi->update(['last_session_at' => '2026-07-11 14:30:00']);
        $sari->update(['trial_date' => '2026-08-03 17:00:00']);
        $eka->update([
            'cuti_from' => '2026-07-01',
            'cuti_until' => '2026-07-31',
            'last_session_at' => '2026-07-12 09:00:00',
        ]);
        $sariEnrollment->update([
            'teacher_id' => $teacherDebora->id,
            'effective_date' => '2026-07-01',
            'status' => Enrollment::STATUS_ACTIVE,
        ]);

        $userPrimary = Enrollment::create([
            'student_id' => $andi->id,
            'package_id' => Package::where('code', 'PIANO_HOBBY_30')->sole()->id,
            'teacher_id' => $teacherDebora->id,
            'effective_date' => '2026-07-01',
            'status' => Enrollment::STATUS_ACTIVE,
            'is_primary' => true,
        ]);
        $andi->enrollments()->whereKeyNot($userPrimary->id)->update(['is_primary' => false]);
        $andi->update(['primary_enrollment_id' => $userPrimary->id]);

        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));
        $this->seed(StudentSeeder::class);

        $this->assertSame(5, Student::count());
        $this->assertSame(5, Enrollment::count());
        $this->assertSame('2026-07-11 14:30:00', $andi->fresh()->last_session_at->toDateTimeString());
        $this->assertSame('2026-08-03 17:00:00', $sari->fresh()->trial_date->toDateTimeString());
        $this->assertSame('2026-07-01', $eka->fresh()->cuti_from->toDateString());
        $this->assertSame('2026-07-31', $eka->fresh()->cuti_until->toDateString());
        $this->assertSame('2026-07-12 09:00:00', $eka->fresh()->last_session_at->toDateTimeString());

        $this->assertSame($teacherDebora->id, $sariEnrollment->fresh()->teacher_id);
        $this->assertSame('2026-07-01', $sariEnrollment->fresh()->effective_date->toDateString());
        $this->assertSame(Enrollment::STATUS_ACTIVE, $sariEnrollment->fresh()->status);
        $this->assertSame($userPrimary->id, $andi->fresh()->primary_enrollment_id);
        $this->assertSame(1, (int) $userPrimary->fresh()->is_primary);
    }
}
