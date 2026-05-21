<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KalenderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function ownerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');
        return $user;
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        return $user;
    }

    private function auditorUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Auditor');
        return $user;
    }

    public function test_owner_dapat_akses_kalender(): void
    {
        $response = $this->actingAs($this->ownerUser())->get(route('kalender.index'));
        $response->assertStatus(200);
    }

    public function test_admin_dapat_akses_kalender(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('kalender.index'));
        $response->assertStatus(200);
    }

    public function test_auditor_dapat_akses_kalender(): void
    {
        $response = $this->actingAs($this->auditorUser())->get(route('kalender.index'));
        $response->assertStatus(200);
    }

    public function test_tamu_tidak_dapat_akses_kalender(): void
    {
        $response = $this->get(route('kalender.index'));
        $response->assertRedirect(route('login'));
    }
}
