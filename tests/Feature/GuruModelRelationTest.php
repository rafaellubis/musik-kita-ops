<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruModelRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_dapat_terhubung_ke_user(): void
    {
        $user    = User::factory()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $teacher->user->id);
    }

    public function test_user_dapat_akses_teacher(): void
    {
        $user    = User::factory()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($teacher->id, $user->teacher->id);
    }

    public function test_user_id_nullable(): void
    {
        $teacher = Teacher::factory()->create(['user_id' => null]);
        $this->assertNull($teacher->user);
    }

    public function test_role_guru_ada(): void
    {
        // Test seeder secara eksplisit — setUp() tidak membuat role ini
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->assertDatabaseHas('roles', ['name' => 'Guru', 'guard_name' => 'web']);
    }
}
