<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed role yang dibutuhkan sebelum setiap test.
     * RefreshDatabase mereset DB tiap test, jadi role harus dibuat ulang di sini.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Buat role yang dibutuhkan (sama seperti RoleSeeder)
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    /**
     * Helper: buat user dengan role Admin.
     */
    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        return $user;
    }

    /**
     * Helper: buat user dengan role Owner.
     */
    private function ownerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');
        return $user;
    }

    public function test_import_index_accessible_by_admin(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('import.index'))
            ->assertStatus(200)
            ->assertSee('Import Murid dari Excel');
    }

    public function test_import_index_inaccessible_by_auditor(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Auditor');

        $this->actingAs($user)
            ->get(route('import.index'))
            ->assertStatus(403);
    }

    public function test_download_template_returns_xlsx(): void
    {
        Excel::fake();

        $this->actingAs($this->adminUser())
            ->get(route('import.template'))
            ->assertStatus(200);

        Excel::assertDownloaded('template-import-murid.xlsx');
    }

    public function test_validate_rejects_non_xlsx_file(): void
    {
        $file = UploadedFile::fake()->create('data.csv', 100, 'text/csv');

        $this->actingAs($this->adminUser())
            ->post(route('import.validate'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_confirm_redirects_with_error_if_no_session(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('import.confirm'))
            ->assertRedirect(route('import.index'))
            ->assertSessionHas('error');
    }

    public function test_cancel_clears_session_and_redirects(): void
    {
        $this->actingAs($this->adminUser())
            ->withSession(['import_preview' => [
                'valid'    => [],
                'overwrite'=> [],
                'errors'   => [],
            ]])
            ->post(route('import.cancel'))
            ->assertRedirect(route('import.index'))
            ->assertSessionHas('info')
            ->assertSessionMissing('import_preview');  // verifikasi controller menghapus session
    }

    public function test_import_index_redirects_guest_to_login(): void
    {
        $this->get(route('import.index'))
            ->assertRedirect(route('login'));
    }

    public function test_import_index_accessible_by_owner(): void
    {
        $this->actingAs($this->ownerUser())
            ->get(route('import.index'))
            ->assertStatus(200);
    }
}
