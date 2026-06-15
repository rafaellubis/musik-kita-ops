<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleBasedRedirectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Guru', 'Admin', 'Owner'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_authenticated_guru_visiting_login_gets_redirected_to_guru_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/guru/dashboard');
    }

    public function test_authenticated_guru_visiting_root_gets_redirected_to_guru_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect('/guru/dashboard');
    }

    public function test_authenticated_admin_visiting_login_gets_redirected_to_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/dashboard');
    }

    public function test_guru_with_intended_admin_dashboard_gets_redirected_to_guru_dashboard(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('Guru');

        // Simulasikan intended URL di session ke /dashboard
        $response = $this->withSession(['url.intended' => route('dashboard', [], false)])
            ->post('/login', [
                'login' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect('/guru/dashboard');
    }
}
