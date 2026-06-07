<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Guru', 'Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->guruUser = User::factory()->create(['email_verified_at' => now()]);
        $this->guruUser->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $this->guruUser->id]);
    }

    public function test_dashboard_tidak_punya_quick_link_laporan(): void
    {
        $this->actingAs($this->guruUser)
            ->get('/guru/dashboard')
            ->assertOk()
            ->assertDontSee('Isi laporan perkembangan murid bulanan', false);
    }

    public function test_layout_menampilkan_menu_laporan_progress_di_navigasi(): void
    {
        $response = $this->actingAs($this->guruUser)->get('/guru/dashboard');

        $response->assertOk()
            ->assertSee('Laporan Progress', false)
            ->assertSee(route('guru.laporan.index'), false);
    }

    public function test_bottom_nav_tidak_punya_tombol_keluar(): void
    {
        $html = $this->actingAs($this->guruUser)
            ->get('/guru/dashboard')
            ->assertOk()
            ->getContent();

        // Bottom nav mobile: class unik di layout guru
        $marker = '<nav class="lg:hidden fixed bottom-0';
        $start = strrpos($html, $marker);
        $this->assertNotFalse($start);
        $bottomNav = substr($html, $start);
        $this->assertStringNotContainsString('Keluar', $bottomNav);
    }

    public function test_topbar_mobile_punya_tombol_keluar(): void
    {
        $html = $this->actingAs($this->guruUser)
            ->get('/guru/dashboard')
            ->assertOk()
            ->getContent();

        $marker = 'class="lg:hidden flex items-center gap-2 shrink-0"';
        $start = strpos($html, $marker);
        $this->assertNotFalse($start);
        $topbarActions = substr($html, $start, 800);
        $this->assertStringContainsString(route('logout'), $topbarActions);
        $this->assertStringContainsString('Keluar', $topbarActions);
    }

    public function test_halaman_laporan_menyalakan_menu_aktif(): void
    {
        $this->actingAs($this->guruUser)
            ->get('/guru/laporan')
            ->assertOk()
            ->assertSee('Laporan Progress', false)
            ->assertSee(route('guru.laporan.index'), false);
    }
}
