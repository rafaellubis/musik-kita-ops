<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollmentLeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_submit_leave_for_another_students_class(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('Admin'));
        $student = Student::factory()->create(['status' => 'Aktif']);
        $foreign = Enrollment::factory()->create();
        $this->post(route('students.start-cuti', $student), [
            'enrollment_id' => $foreign->id,
            'cuti_from' => today()->toDateString(),
            'cuti_until' => today()->addDays(5)->toDateString(),
            'reason' => 'Liburan',
        ])->assertSessionHasErrors('enrollment_id');
        $this->post(route('students.return-from-cuti', $student), [
            'enrollment_id' => $foreign->id,
        ])->assertSessionHasErrors('enrollment_id');
        $this->assertSame('Aktif', $student->fresh()->status);
        $this->assertSame('ACTIVE', $foreign->fresh()->status);
        $this->assertDatabaseCount('enrollment_leaves', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_auditor_cannot_start_or_return_leave(): void
    {
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('Auditor'));
        $enrollment = Enrollment::factory()->create();
        foreach (['students.start-cuti', 'students.return-from-cuti'] as $route) {
            $this->post(route($route, $enrollment->student), ['enrollment_id' => $enrollment->id])->assertForbidden();
        }
        $this->assertSame('ACTIVE', $enrollment->fresh()->status);
        $this->assertDatabaseCount('enrollment_leaves', 0);
    }
}
