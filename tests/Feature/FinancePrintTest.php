<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinancePrintTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function owner_bisa_akses_finance_print(): void
    {
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)
            ->get('/reports/finance/print?year=2026&month=1');

        $response->assertStatus(200);
        $response->assertViewIs('reports.finance-print');
        $response->assertSee('Laporan Keuangan');
    }

    /** @test */
    public function admin_tidak_bisa_akses_finance_print(): void
    {
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)
            ->get('/reports/finance/print?year=2026&month=1');

        $response->assertStatus(403);
    }

    /** @test */
    public function guest_redirect_ke_login(): void
    {
        $response = $this->get('/reports/finance/print');
        $response->assertRedirect('/login');
    }
}
