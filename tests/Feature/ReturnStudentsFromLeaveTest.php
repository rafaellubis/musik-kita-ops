<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\EnrollmentLeave;
use App\Models\Student;
use App\Models\User;
use App\Notifications\MuridKembaliDariCutiNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturnStudentsFromLeaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-15 12:00:00');
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_scheduler_returns_only_expired_class_even_when_student_is_active(): void
    {
        $admin = User::factory()->create()->assignRole('Admin');
        $student = Student::factory()->create(['status' => 'Aktif']);
        $expired = Enrollment::factory()->create(['student_id' => $student->id, 'status' => 'ON_LEAVE']);
        $ongoing = Enrollment::factory()->create(['student_id' => $student->id, 'status' => 'ON_LEAVE']);
        Enrollment::factory()->create(['student_id' => $student->id, 'status' => 'ACTIVE']);
        $oldLeave = $expired->leaves()->create(['cuti_from' => '2026-07-01', 'cuti_until' => '2026-07-14', 'reason' => 'Liburan']);
        $ongoing->leaves()->create(['cuti_from' => '2026-07-01', 'cuti_until' => '2026-07-31', 'reason' => 'Liburan']);

        $this->artisan('students:return-from-leave')->assertSuccessful();

        $this->assertSame('ACTIVE', $expired->fresh()->status);
        $this->assertSame('ON_LEAVE', $ongoing->fresh()->status);
        $this->assertSame('Aktif', $student->fresh()->status);
        $this->assertNotNull($oldLeave->fresh()->completed_at);
        $this->assertSame('2026-07-14', $oldLeave->fresh()->cuti_until->toDateString());
        Notification::assertSentTo($admin, MuridKembaliDariCutiNotification::class);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'Enrollment', 'entity_id' => $expired->id]);
    }

    public function test_last_day_is_still_leave_and_return_is_next_day(): void
    {
        $student = Student::factory()->create(['status' => 'Cuti']);
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id, 'status' => 'ON_LEAVE']);
        $leave = $enrollment->leaves()->create(['cuti_from' => '2026-07-01', 'cuti_until' => '2026-07-15', 'reason' => 'Liburan']);

        $this->artisan('students:return-from-leave')->assertSuccessful();
        $this->assertSame('ON_LEAVE', $enrollment->fresh()->status);
        $this->assertNull($leave->fresh()->completed_at);
        Notification::assertNothingSent();

        Carbon::setTestNow('2026-07-16 00:01:00');
        $this->artisan('students:return-from-leave')->assertSuccessful();
        $this->assertSame('ACTIVE', $enrollment->fresh()->status);
        $this->assertSame('Aktif', $student->fresh()->status);
    }

    public function test_future_leave_starts_without_pausing_other_class(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $selected = Enrollment::factory()->create(['student_id' => $student->id, 'status' => 'ACTIVE']);
        $other = Enrollment::factory()->create(['student_id' => $student->id, 'status' => 'ACTIVE']);
        $selected->leaves()->create(['cuti_from' => '2026-07-16', 'cuti_until' => '2026-07-31', 'reason' => 'Liburan']);

        $this->artisan('students:return-from-leave')->assertSuccessful();
        $this->assertSame('ACTIVE', $selected->fresh()->status);
        Carbon::setTestNow('2026-07-16 00:01:00');
        $this->artisan('students:return-from-leave')->assertSuccessful();
        $this->assertSame('ON_LEAVE', $selected->fresh()->status);
        $this->assertSame('ACTIVE', $other->fresh()->status);
        $this->assertSame('Aktif', $student->fresh()->status);
    }
}
