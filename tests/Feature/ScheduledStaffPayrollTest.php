<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\StaffPayrollSlip;
use App\Models\User;
use App\Services\StaffPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduledStaffPayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->owner = User::factory()->create()->assignRole('Owner');

        ExpenseCategory::firstOrCreate(
            ['code' => 'GAJI_STAFF'],
            ['name' => 'Gaji Staff Non-Guru', 'is_active' => true, 'sort_order' => 5]
        );
    }

    private function activeEmployee(string $code = 'STF-0001'): Employee
    {
        return Employee::create([
            'employee_code' => $code,
            'full_name'     => 'Ayu Admin',
            'position'      => 'Admin Operasional',
            'base_salary'   => 4_500_000,
            'is_active'     => true,
        ]);
    }

    public function test_generate_slips_creates_calculated_slips_for_active_employees(): void
    {
        $employee = $this->activeEmployee();

        $this->artisan('staff-payroll:generate-slips')->assertSuccessful();

        $slip = StaffPayrollSlip::where('employee_id', $employee->id)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->first();

        $this->assertNotNull($slip);
        $this->assertEquals(4_500_000, $slip->base_salary);
        $this->assertEquals(StaffPayrollSlip::STATUS_CALCULATED, $slip->status);
        $this->assertEquals($this->owner->id, $slip->created_by);
        $this->assertStringStartsWith(
            'LMK/SLIP/' . now()->format('Y/m') . '/',
            $slip->slip_number,
        );
    }

    public function test_generate_slips_skips_paid_slips(): void
    {
        $employee = $this->activeEmployee();
        $service  = app(StaffPayrollService::class);

        $service->generateAllSlips(now()->year, now()->month, $this->owner->id);
        $slip = StaffPayrollSlip::where('employee_id', $employee->id)->first();
        $slip->update(['status' => StaffPayrollSlip::STATUS_PAID]);

        $this->artisan('staff-payroll:generate-slips')->assertSuccessful();

        $this->assertEquals(StaffPayrollSlip::STATUS_PAID, $slip->fresh()->status);
        $this->assertEquals(1, StaffPayrollSlip::where('employee_id', $employee->id)->count());
    }

    public function test_generate_slips_creates_audit_log_summary(): void
    {
        $this->activeEmployee();

        $this->artisan('staff-payroll:generate-slips')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'entity_label' => 'Scheduler Generate Slip Gaji Staff',
            'action'       => AuditLog::ACTION_CREATE,
        ]);
    }
}
