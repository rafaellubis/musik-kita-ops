<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginWithUsernameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Guru', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_login_dengan_username_berhasil(): void
    {
        $user = User::factory()->create([
            'username'          => 'thomas',
            'email'             => 'thomas.1@musikkita.local',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Guru');

        $this->post('/login', ['login' => 'thomas', 'password' => 'password'])
             ->assertRedirect('/guru/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_dengan_email_masih_berhasil(): void
    {
        $user = User::factory()->create([
            'username'          => 'thomas',
            'email'             => 'thomas.1@musikkita.local',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Admin');

        $this->post('/login', ['login' => 'thomas.1@musikkita.local', 'password' => 'password'])
             ->assertRedirect('/dashboard');
    }

    public function test_login_username_salah_gagal(): void
    {
        User::factory()->create([
            'username' => 'thomas',
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', ['login' => 'salah', 'password' => 'password']);

        $this->assertGuest();
    }
}
