<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NicknameUniqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $user = User::factory()->create()->assignRole('Admin');
        $this->actingAs($user);
    }

    // --- STORE ---

    /** Nickname baru dan unik → boleh disimpan */
    public function test_store_nickname_unik_berhasil(): void
    {
        $this->post(route('students.store'), [
            'full_name' => 'Budi Santoso',
            'gender'    => 'L',
            'nickname'  => 'Budi',
        ])->assertSessionHasNoErrors()
          ->assertRedirect();
    }

    /** Nickname duplikat dengan murid lain → gagal validasi */
    public function test_store_nickname_duplikat_gagal(): void
    {
        Student::factory()->create(['nickname' => 'Budi']);

        $this->post(route('students.store'), [
            'full_name' => 'Budi Prasetyo',
            'gender'    => 'L',
            'nickname'  => 'Budi',
        ])->assertSessionHasErrors(['nickname']);
    }

    /** Nickname kosong (null) → boleh banyak, tidak bentrok */
    public function test_store_nickname_kosong_boleh_banyak(): void
    {
        Student::factory()->create(['nickname' => null]);

        $this->post(route('students.store'), [
            'full_name' => 'Clara Putri',
            'gender'    => 'P',
            'nickname'  => '',
        ])->assertSessionHasNoErrors()
          ->assertRedirect();
    }

    /** Input lowercase saat Title Case sudah ada → ditangkap duplikat */
    public function test_store_nickname_case_insensitive_gagal(): void
    {
        Student::factory()->create(['nickname' => 'Andi']);

        $this->post(route('students.store'), [
            'full_name' => 'Andi Prasetyo',
            'gender'    => 'L',
            'nickname'  => 'andi',
        ])->assertSessionHasErrors(['nickname']);
    }

    // --- UPDATE ---

    /** Edit murid tanpa ubah nickname → tidak bentrok dengan datanya sendiri */
    public function test_update_nickname_sendiri_berhasil(): void
    {
        $student = Student::factory()->create(['nickname' => 'Budi']);

        $this->put(route('students.update', $student), [
            'full_name' => $student->full_name,
            'gender'    => $student->gender,
            'nickname'  => 'Budi',
        ])->assertSessionHasNoErrors()
          ->assertRedirect();
    }

    /** Edit murid, ganti nickname ke milik murid lain → gagal validasi */
    public function test_update_nickname_milik_murid_lain_gagal(): void
    {
        Student::factory()->create(['nickname' => 'Budi']);
        $student = Student::factory()->create(['nickname' => 'Andi']);

        $this->put(route('students.update', $student), [
            'full_name' => $student->full_name,
            'gender'    => $student->gender,
            'nickname'  => 'Budi',
        ])->assertSessionHasErrors(['nickname']);
    }

    /** Edit murid, hapus nickname (jadi kosong) → boleh */
    public function test_update_hapus_nickname_berhasil(): void
    {
        $student = Student::factory()->create(['nickname' => 'Budi']);

        $this->put(route('students.update', $student), [
            'full_name' => $student->full_name,
            'gender'    => $student->gender,
            'nickname'  => '',
        ])->assertSessionHasNoErrors()
          ->assertRedirect();
    }
}
