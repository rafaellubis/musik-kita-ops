<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Guru',    'guard_name' => 'web']);
    }

    // ===== Helper =====

    private function owner(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Owner');
        return $user;
    }

    private function makeTeacher(array $attrs = []): Teacher
    {
        return Teacher::create(array_merge([
            'code'        => 'T' . rand(1000, 9999),
            'name'        => 'Teacher Test',
            'joined_date' => now()->toDateString(),
            'is_active'   => true,
        ], $attrs));
    }

    // ===== Akses =====

    public function test_owner_dapat_akses_halaman_users(): void
    {
        $response = $this->actingAs($this->owner())->get(route('users.index'));
        $response->assertOk();
    }

    public function test_admin_tidak_bisa_akses_halaman_users(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Admin');
        $this->actingAs($admin)->get(route('users.index'))->assertForbidden();
    }

    public function test_auditor_tidak_bisa_akses_halaman_users(): void
    {
        $auditor = User::factory()->create(['is_active' => true]);
        $auditor->assignRole('Auditor');
        $this->actingAs($auditor)->get(route('users.index'))->assertForbidden();
    }

    // ===== Buat User =====

    public function test_owner_bisa_buat_user_admin(): void
    {
        $this->actingAs($this->owner())->post(route('users.store'), [
            'name'     => 'Admin Baru',
            'email'    => 'admin.baru@musikkita.local',
            'role'     => 'Admin',
            'password' => 'password123',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'admin.baru@musikkita.local')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Admin'));
        $this->assertTrue($user->is_active);
    }

    public function test_owner_bisa_buat_user_guru_dengan_link_teacher(): void
    {
        $teacher = $this->makeTeacher(['name' => 'Thomas', 'code' => 'THO']);

        $this->actingAs($this->owner())->post(route('users.store'), [
            'name'       => 'Thomas Login',
            'email'      => 'thomas@musikkita.local',
            'role'       => 'Guru',
            'password'   => 'password123',
            'teacher_id' => $teacher->id,
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'thomas@musikkita.local')->first();
        $this->assertTrue($user->hasRole('Guru'));
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'user_id' => $user->id]);
    }

    public function test_buat_user_guru_tanpa_teacher_id_ditolak(): void
    {
        $this->actingAs($this->owner())->post(route('users.store'), [
            'name'     => 'Guru Tanpa Teacher',
            'email'    => 'tanpa@musikkita.local',
            'role'     => 'Guru',
            'password' => 'password123',
        ])->assertSessionHasErrors('teacher_id');
    }

    // ===== Edit User =====

    public function test_owner_bisa_edit_nama_dan_email(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->put(route('users.update', $target), [
            'name'     => 'Nama Baru',
            'username' => $target->username,
            'email'    => 'baru@musikkita.local',
            'role'     => 'Admin',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Nama Baru']);
    }

    public function test_owner_bisa_buat_user_tanpa_username_auto_generate(): void
    {
        $this->actingAs($this->owner())->post(route('users.store'), [
            'name'     => 'Thomas Login',
            'email'    => 'thomas.bar@musikkita.local',
            'role'     => 'Admin',
            'password' => 'password123',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'thomas.bar@musikkita.local')->first();
        $this->assertNotNull($user);
        $this->assertEquals('thomaslogin', $user->username);
    }

    public function test_edit_ganti_role_guru_ke_admin_melepas_teacher(): void
    {
        $teacher = $this->makeTeacher();
        $guru = User::factory()->create(['is_active' => true]);
        $guru->assignRole('Guru');
        $teacher->update(['user_id' => $guru->id]);

        $this->actingAs($this->owner())->put(route('users.update', $guru), [
            'name'     => $guru->name,
            'username' => $guru->username,
            'email'    => $guru->email,
            'role'     => 'Admin',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'user_id' => null]);
        $this->assertTrue($guru->fresh()->hasRole('Admin'));
    }

    // ===== Reset Password =====

    public function test_owner_bisa_reset_password_user_lain(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->post(route('users.reset-password', $target), [
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('users.index'));

        $this->assertTrue(Hash::check('newpassword123', $target->fresh()->password));
    }

    public function test_reset_password_tidak_cocok_ditolak(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->post(route('users.reset-password', $target), [
            'password'              => 'newpassword123',
            'password_confirmation' => 'berbeda456',
        ])->assertSessionHasErrors('password');
    }

    // ===== Toggle Active =====

    public function test_owner_bisa_nonaktifkan_user_lain(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->post(route('users.toggle-active', $target))
            ->assertRedirect(route('users.index'));

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_owner_tidak_bisa_nonaktifkan_diri_sendiri(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('users.toggle-active', $owner))
            ->assertRedirect(route('users.index'));

        // Status tidak berubah
        $this->assertTrue($owner->fresh()->is_active);
    }

    // ===== Hapus =====

    public function test_hapus_user_nonaktif_tanpa_audit_log_berhasil(): void
    {
        $target = User::factory()->create(['is_active' => false]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_hapus_gagal_jika_user_masih_aktif(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_hapus_gagal_jika_user_punya_audit_log(): void
    {
        $target = User::factory()->create(['is_active' => false]);
        $target->assignRole('Admin');

        AuditLog::create([
            'user_id'      => $target->id,
            'user_name'    => $target->name,
            'action'       => 'LOGIN',
            'entity_type'  => null,
            'entity_id'    => null,
            'entity_label' => null,
        ]);

        $this->actingAs($this->owner())->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_owner_tidak_bisa_hapus_akun_sendiri(): void
    {
        $owner = $this->owner();
        $owner->update(['is_active' => false]);

        $this->actingAs($owner)->delete(route('users.destroy', $owner))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }
}
