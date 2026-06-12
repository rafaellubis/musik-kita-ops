<?php

namespace Tests\Feature;

use App\Models\PettyCashExpense;
use App\Models\PettyCashTopup;
use App\Models\User;
use App\Services\PettyCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
