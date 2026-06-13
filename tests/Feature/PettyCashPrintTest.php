<?php

namespace Tests\Feature;

use App\Models\PettyCashExpense;
use App\Models\PettyCashTopup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PettyCashPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    /** @test */
    public function petty_cash_print_menampilkan_mutasi_bulan(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        PettyCashTopup::factory()->create([
            'amount'       => 500_000,
            'topup_date'   => '2026-06-01',
            'topup_number' => 'PCU/2026/06/0001',
            'description'  => 'Isi saldo awal',
        ]);
        PettyCashExpense::factory()->create([
            'amount'         => 75_000,
            'expense_date'   => '2026-06-05',
            'expense_number' => 'PCE/2026/06/0001',
            'description'    => 'Beli ATK',
        ]);

        $response = $this->actingAs($owner)->get(route('petty-cash.print', [
            'year'  => 2026,
            'month' => 6,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Laporan Petty Cash');
        $response->assertSee('PCU/2026/06/0001');
        $response->assertSee('PCE/2026/06/0001');
        $response->assertSee('500.000');
    }

    /** @test */
    public function guest_tidak_bisa_akses_petty_cash_print(): void
    {
        $this->get(route('petty-cash.print', ['year' => 2026, 'month' => 6]))
            ->assertRedirect(route('login'));
    }
}
