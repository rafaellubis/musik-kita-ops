<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AbsensiPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Buat role Spatie yang dibutuhkan
        Role::create(['name' => 'Owner']);
        Role::create(['name' => 'Admin']);
        Role::create(['name' => 'Auditor']);
    }

    public function test_guest_cannot_access_print_page(): void
    {
        $response = $this->get(route('absensi.print'));
        $response->assertRedirect(route('login'));
    }

    public function test_authorized_roles_can_access_print_page(): void
    {
        $roles = ['Owner', 'Admin', 'Auditor'];

        foreach ($roles as $roleName) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $response = $this->actingAs($user)->get(route('absensi.print'));
            $response->assertStatus(200);
        }
    }
}
