<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiKelasModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_package_accessor_via_primary_enrollment(): void
    {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->for($student)->create([
            'is_primary' => true,
            'status'     => 'ACTIVE',
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $this->assertNotNull($student->fresh()->package);
        $this->assertEquals($enrollment->package_id, $student->fresh()->package->id);
    }

    public function test_student_package_accessor_null_when_no_primary_enrollment(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);

        $this->assertNull($student->package);
        $this->assertNull($student->assignedTeacher);
    }

    public function test_student_dapat_punya_dua_enrollment_active(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);

        Enrollment::factory()->for($student)->create([
            'is_primary' => true,
            'status'     => 'ACTIVE',
        ]);
        Enrollment::factory()->for($student)->create([
            'is_primary' => false,
            'status'     => 'ACTIVE',
        ]);

        $this->assertEquals(2, $student->enrollments()->active()->count());
    }
}
