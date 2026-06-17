<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\MuridKembaliDariCutiNotification;
use App\Services\EnrollmentSessionCleanupService;
use App\Services\InvoiceService;
use App\Services\StudentLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturnStudentsFromLeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);

        $this->admin = User::factory()->create()->assignRole('Admin');
        $this->owner = User::factory()->create()->assignRole('Owner');
    }

    public function test_cuti_until_kemarin_kembali_aktif_dengan_notif_dan_audit(): void
    {
        Notification::fake();

        $student = Student::factory()->create([
            'status'     => 'Cuti',
            'cuti_from'  => now()->subDays(30)->toDateString(),
            'cuti_until' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('students:return-from-leave')->assertSuccessful();

        $student->refresh();
        $this->assertEquals('Aktif', $student->status);
        $this->assertNull($student->cuti_from);
        $this->assertNull($student->cuti_until);

        Notification::assertSentTo($this->admin, MuridKembaliDariCutiNotification::class);
        Notification::assertSentTo($this->owner, MuridKembaliDariCutiNotification::class);

        $this->assertDatabaseHas('audit_logs', [
            'action'       => AuditLog::ACTION_LIFECYCLE,
            'entity_type'  => 'Student',
            'entity_id'    => $student->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_label' => 'Scheduler Cuti → Aktif',
        ]);
    }

    public function test_cuti_until_masa_depan_tidak_diproses(): void
    {
        Notification::fake();

        $student = Student::factory()->create([
            'status'     => 'Cuti',
            'cuti_from'  => now()->toDateString(),
            'cuti_until' => now()->addDays(5)->toDateString(),
        ]);

        $this->artisan('students:return-from-leave')->assertSuccessful();

        $this->assertEquals('Cuti', $student->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_enrollment_on_leave_dikembalikan_ke_active(): void
    {
        Notification::fake();

        $teacher    = Teacher::factory()->create();
        $package    = Package::factory()->create();
        $student    = Student::factory()->create([
            'status'     => 'Cuti',
            'cuti_from'  => now()->subDays(10)->toDateString(),
            'cuti_until' => now()->subDay()->toDateString(),
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $package->id,
            'teacher_id' => $teacher->id,
            'status'     => Enrollment::STATUS_ON_LEAVE,
        ]);

        $this->artisan('students:return-from-leave')->assertSuccessful();

        $this->assertEquals(Enrollment::STATUS_ACTIVE, $enrollment->fresh()->status);
    }

    public function test_cuti_until_hari_ini_diproses(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 12:00:00'));

        Notification::fake();

        $student = Student::factory()->create([
            'status'     => 'Cuti',
            'cuti_from'  => '2026-05-18',
            'cuti_until' => '2026-06-18',
        ]);

        $this->artisan('students:return-from-leave')->assertSuccessful();

        $this->assertEquals('Aktif', $student->fresh()->status);

        Carbon::setTestNow();
    }
}
