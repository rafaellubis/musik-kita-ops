<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruCreateAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
    }

    public function test_buat_akun_untuk_guru_aktif(): void
    {
        Teacher::factory()->create([
            'name'      => 'THOMAS',
            'email'     => 'thomas@gmail.com',
            'user_id'   => null,
            'is_active' => true,
        ]);

        $this->artisan('guru:create-accounts')->assertSuccessful();

        $teacher = Teacher::first();
        $this->assertNotNull($teacher->user_id);
        $this->assertEquals('thomas@gmail.com', $teacher->user->email);
        $this->assertTrue($teacher->user->hasRole('Guru'));
    }

    public function test_generate_email_dummy_jika_email_null(): void
    {
        Teacher::factory()->create([
            'name'      => 'THOMAS',
            'email'     => null,
            'user_id'   => null,
            'is_active' => true,
        ]);

        $this->artisan('guru:create-accounts')->assertSuccessful();

        $this->assertEquals('thomas@musikkita.local', Teacher::first()->user->email);
    }

    public function test_skip_guru_yang_sudah_punya_akun(): void
    {
        $existingUser = User::factory()->create();
        Teacher::factory()->create(['user_id' => $existingUser->id, 'is_active' => true]);

        $this->artisan('guru:create-accounts')->assertSuccessful();

        // tidak ada user baru dibuat
        $this->assertEquals(1, User::count());
    }

    public function test_skip_guru_nonaktif(): void
    {
        Teacher::factory()->create(['is_active' => false, 'user_id' => null]);

        $this->artisan('guru:create-accounts')->assertSuccessful();

        $this->assertEquals(0, User::count());
    }

    public function test_password_format_nama_lowercase_tanpa_spasi(): void
    {
        Teacher::factory()->create([
            'name'      => 'T. HADI',
            'email'     => 'hadi@gmail.com',
            'user_id'   => null,
            'is_active' => true,
        ]);

        $this->artisan('guru:create-accounts');

        $this->assertTrue(Hash::check('t.hadi', User::first()->password));
    }
}
