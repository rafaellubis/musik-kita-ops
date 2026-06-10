<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\StaffPayrollItem;
use App\Models\StaffPayrollSlip;
use App\Models\User;
use App\Services\StaffPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        ExpenseCategory::firstOrCreate(
            ['code' => 'GAJI_STAFF'],
            ['name' => 'Gaji Staff Non-Guru', 'is_active' => true, 'sort_order' => 5]
        );
    }

    private function ownerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        return $user;
    }

    private function activeEmployee(): Employee
    {
        return Employee::create([
            'employee_code' => 'STF-0001',
            'full_name'     => 'Ayu Admin',
            'position'      => 'Admin Operasional',
            'base_salary'   => 4_500_000,
            'is_active'     => true,
        ]);
    }

    public function test_generate_slips_for_active_employees(): void
    {
        $owner    = $this->ownerUser();
        $employee = $this->activeEmployee();

        $service = app(StaffPayrollService::class);
        $report  = $service->generateAllSlips(2026, 6, $owner->id);

        $this->assertEquals(1, $report['created']);
        $this->assertEquals(0, $report['updated']);
        $this->assertEquals(0, $report['skipped']);

        $slip = StaffPayrollSlip::where('employee_id', $employee->id)
            ->where('year', 2026)
            ->where('month', 6)
            ->first();

        $this->assertNotNull($slip);
        $this->assertEquals(4_500_000, $slip->base_salary);
        $this->assertEquals(4_500_000, $slip->net_salary);
        $this->assertEquals(StaffPayrollSlip::STATUS_CALCULATED, $slip->status);
        $this->assertStringStartsWith('GAJI/2026/06/', $slip->slip_number);
    }

    public function test_add_items_recalculates_net_salary(): void
    {
        $owner    = $this->ownerUser();
        $employee = $this->activeEmployee();
        $service  = app(StaffPayrollService::class);

        $service->generateAllSlips(2026, 6, $owner->id);
        $slip = StaffPayrollSlip::where('employee_id', $employee->id)->first();

        $service->addItem($slip, [
            'item_type'   => StaffPayrollItem::TYPE_ALLOWANCE,
            'item_code'   => 'TUNJ_TRANSPORT',
            'description' => 'Tunjangan transport Juni',
            'amount'      => 300_000,
        ]);

        $service->addItem($slip, [
            'item_type'   => StaffPayrollItem::TYPE_DEDUCTION,
            'item_code'   => 'BPJS',
            'description' => 'Potongan BPJS',
            'amount'      => 150_000,
        ]);

        $slip->refresh();
        $this->assertEquals(300_000, $slip->total_allowances);
        $this->assertEquals(150_000, $slip->total_deductions);
        $this->assertEquals(4_650_000, $slip->net_salary);
        $this->assertEquals(StaffPayrollSlip::STATUS_CALCULATED, $slip->status);
    }

    public function test_mark_paid_creates_gaji_staff_expense(): void
    {
        $owner    = $this->ownerUser();
        $employee = $this->activeEmployee();
        $service  = app(StaffPayrollService::class);

        $service->generateAllSlips(2026, 6, $owner->id);
        $slip = StaffPayrollSlip::where('employee_id', $employee->id)->first();

        $paid = $service->markPaid($slip, $owner->id);

        $this->assertEquals(StaffPayrollSlip::STATUS_PAID, $paid->status);
        $this->assertNotNull($paid->expense_id);
        $this->assertNotNull($paid->paid_at);

        $expense = Expense::find($paid->expense_id);
        $this->assertNotNull($expense);
        $this->assertEquals(4_500_000, $expense->amount);
        $this->assertEquals('GAJI_STAFF', $expense->category->code);
        $this->assertStringContainsString('Ayu Admin', $expense->description);
    }

    public function test_paid_slip_cannot_add_items(): void
    {
        $owner    = $this->ownerUser();
        $employee = $this->activeEmployee();
        $service  = app(StaffPayrollService::class);

        $service->generateAllSlips(2026, 6, $owner->id);
        $slip = StaffPayrollSlip::where('employee_id', $employee->id)->first();
        $service->markPaid($slip, $owner->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slip sudah PAID');

        $service->addItem($slip->fresh(), [
            'item_type'   => StaffPayrollItem::TYPE_ALLOWANCE,
            'item_code'   => 'BONUS',
            'description' => 'Bonus',
            'amount'      => 100_000,
        ]);
    }

    public function test_void_paid_deletes_expense_and_reverts_status(): void
    {
        $owner    = $this->ownerUser();
        $employee = $this->activeEmployee();
        $service  = app(StaffPayrollService::class);

        $service->generateAllSlips(2026, 6, $owner->id);
        $slip = StaffPayrollSlip::where('employee_id', $employee->id)->first();
        $service->markPaid($slip, $owner->id);

        $expenseId = $slip->fresh()->expense_id;

        $reverted = $service->voidPaid($slip->fresh());

        $this->assertEquals(StaffPayrollSlip::STATUS_CALCULATED, $reverted->status);
        $this->assertNull($reverted->expense_id);
        $this->assertNull(Expense::find($expenseId));
    }

    public function test_owner_can_generate_via_http(): void
    {
        $owner = $this->ownerUser();
        $this->activeEmployee();

        $response = $this->actingAs($owner)->post(route('staff-payrolls.generate'), [
            'year'  => 2026,
            'month' => 6,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals(1, StaffPayrollSlip::count());
    }
}
