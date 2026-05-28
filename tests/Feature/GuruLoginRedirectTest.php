<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Guru', 'Owner', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_guru_redirect_ke_guru_dashboard(): void
    {
        $user = User::factory()->create([
            'email'             => 'guru@test.com',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $user->id]);

        $this->post('/login', ['email' => 'guru@test.com', 'password' => 'password'])
             ->assertRedirect('/guru/dashboard');
    }

    public function test_owner_redirect_ke_dashboard(): void
    {
        $user = User::factory()->create([
            'email'             => 'owner@test.com',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Owner');

        $this->post('/login', ['email' => 'owner@test.com', 'password' => 'password'])
             ->assertRedirect('/dashboard');
    }

    public function test_admin_redirect_ke_dashboard(): void
    {
        $user = User::factory()->create([
            'email'             => 'admin@test.com',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Admin');

        $this->post('/login', ['email' => 'admin@test.com', 'password' => 'password'])
             ->assertRedirect('/dashboard');
    }
}
