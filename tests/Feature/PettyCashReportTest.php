<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PettyCashExpense;
use App\Models\PettyCashTopup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PettyCashReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
    }

    private function expenseCategoryAtk(): ExpenseCategory
    {
        return ExpenseCategory::firstOrCreate(
            ['code' => 'ATK'],
            ['name' => 'Alat Tulis & Kantor', 'is_active' => true, 'sort_order' => 7]
        );
    }

    /** @test */
    public function pl_masukkan_topup_tapi_tidak_double_count_petty_expense(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        PettyCashTopup::factory()->create([
            'amount'     => 500_000,
            'topup_date' => '2026-06-10',
        ]);
        PettyCashExpense::factory()->create([
            'amount'       => 100_000,
            'expense_date' => '2026-06-12',
        ]);

        $category = $this->expenseCategoryAtk();
        Expense::create([
            'expense_number'      => 'EXP/2026/06/0001',
            'expense_category_id' => $category->id,
            'amount'              => 200_000,
            'description'         => 'Pengeluaran operasional transfer',
            'expense_date'        => '2026-06-11',
            'payment_method'      => 'TRANSFER',
        ]);

        $response = $this->actingAs($owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 6]));

        $response->assertViewHas('totalPettyCashTopup', 500_000);
        $response->assertViewHas('totalPengeluaranOperasional', 200_000);

        $laba = $response->viewData('labaBersih');
        $this->assertSame(-700_000, $laba);
    }
}
