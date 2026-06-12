<?php

namespace Tests\Feature;

use App\Http\Requests\StorePettyCashExpenseRequest;
use App\Http\Requests\StorePettyCashTopupRequest;
use App\Models\ExpenseCategory;
use App\Models\PettyCashExpense;
use App\Models\PettyCashTopup;
use App\Models\User;
use App\Services\PettyCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PettyCashTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function saldo_petty_cash_adalah_topup_dikurangi_expense(): void
    {
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        PettyCashTopup::factory()->create(['amount' => 500_000, 'topup_date' => '2026-06-01']);
        PettyCashExpense::factory()->create(['amount' => 150_000, 'expense_date' => '2026-06-05']);

        $service = app(PettyCashService::class);
        $this->assertSame(350_000, $service->getCurrentBalance());
    }

    /** @test */
    public function store_petty_cash_expense_request_menolak_jika_saldo_tidak_cukup(): void
    {
        $category = ExpenseCategory::firstOrCreate(
            ['code' => 'ATK'],
            ['name' => 'Alat Tulis & Kantor', 'is_active' => true, 'sort_order' => 7]
        );

        $request = new StorePettyCashExpenseRequest();
        $request->merge([
            'expense_category_id' => $category->id,
            'amount'              => 100_000,
            'description'         => 'Beli ATK',
            'expense_date'        => now()->toDateString(),
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
        $this->assertStringContainsString(
            'Saldo petty cash tidak cukup',
            $validator->errors()->first('amount')
        );
    }

    /** @test */
    public function store_petty_cash_topup_request_menolak_data_tidak_valid(): void
    {
        $request = new StorePettyCashTopupRequest();
        $request->merge([
            'amount'      => 0,
            'topup_date'  => now()->addDay()->toDateString(),
            'description' => '',
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
        $this->assertArrayHasKey('topup_date', $validator->errors()->toArray());
        $this->assertArrayHasKey('description', $validator->errors()->toArray());
    }
}
