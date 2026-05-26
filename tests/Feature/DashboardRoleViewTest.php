<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardRoleViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    // ===== ADMIN: widget yang TIDAK boleh muncul =====

    public function test_admin_tidak_melihat_saldo_kas(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertDontSee('Saldo Kas');
    }

    public function test_admin_tidak_melihat_murid_aktif_kpi(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertDontSee('Murid Aktif');
    }

    public function test_admin_tidak_melihat_aging_piutang(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertDontSee('Aging Piutang');
    }

    public function test_admin_tidak_melihat_slip_honor_belum_dibayarkan(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertDontSee('Slip Honor Belum Dibayarkan');
    }

    public function test_admin_tidak_melihat_statistik_murid(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertDontSee('Statistik Murid');
    }

    public function test_admin_tidak_melihat_kolom_sisa(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertDontSee('Sisa');
    }

    // ===== ADMIN: widget yang HARUS muncul =====

    public function test_admin_melihat_daftar_absensi_hari_ini(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertSee('Daftar Absensi Hari Ini');
    }

    public function test_admin_melihat_pesan_kosong_absensi(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertSee('Tidak ada sesi yang perlu diabsen hari ini.');
    }

    // ===== AUDITOR: tidak berubah =====

    public function test_auditor_masih_melihat_saldo_kas(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Saldo Kas');
    }

    public function test_auditor_masih_melihat_aging_piutang(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertSee('Aging Piutang');
    }

    public function test_auditor_masih_melihat_statistik_murid(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertSee('Statistik Murid');
    }

    public function test_auditor_masih_melihat_slip_honor(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertSee('Slip Honor Belum Dibayarkan');
    }

    public function test_auditor_masih_melihat_kolom_sisa(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertSee('Sisa');
    }

    public function test_auditor_tidak_melihat_daftar_absensi_hari_ini(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertDontSee('Daftar Absensi Hari Ini');
    }

    // ===== OWNER: tidak berubah =====

    public function test_owner_masih_melihat_saldo_kas(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))
            ->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Saldo Kas');
    }

    public function test_owner_masih_melihat_aging_piutang(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))
            ->get(route('dashboard'));
        $response->assertSee('Aging Piutang');
    }

    public function test_owner_masih_melihat_statistik_murid(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))
            ->get(route('dashboard'));
        $response->assertSee('Statistik Murid');
    }

    public function test_owner_masih_melihat_slip_honor(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))
            ->get(route('dashboard'));
        $response->assertSee('Slip Honor Belum Dibayarkan');
    }

    public function test_owner_tidak_melihat_daftar_absensi_hari_ini(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))
            ->get(route('dashboard'));
        $response->assertDontSee('Daftar Absensi Hari Ini');
    }
}
