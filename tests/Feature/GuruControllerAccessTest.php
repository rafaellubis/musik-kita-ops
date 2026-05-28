<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruControllerAccessTest extends TestCase
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

    public function test_guru_akses_dashboard(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/dashboard')->assertOk();
    }

    public function test_guru_akses_jadwal(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/jadwal')->assertOk();
    }

    public function test_guru_akses_honor(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/honor')->assertOk();
    }

    public function test_owner_tidak_bisa_akses_guru_routes(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $owner->assignRole('Owner');

        $this->actingAs($owner)->get('/guru/dashboard')->assertForbidden();
    }

    public function test_guru_tidak_bisa_akses_dashboard_admin(): void
    {
        $this->actingAs($this->guruUser)->get('/dashboard')->assertForbidden();
    }

    public function test_guru_tidak_bisa_akses_teachers_index(): void
    {
        $this->actingAs($this->guruUser)->get('/teachers')->assertForbidden();
    }

    public function test_unauthenticated_redirect_ke_login(): void
    {
        $this->get('/guru/dashboard')->assertRedirect('/login');
    }
}
