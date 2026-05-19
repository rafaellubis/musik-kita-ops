<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_import_index_inaccessible_by_admin(): void
    {
        // Import dipindah ke Owner-only — Admin tidak boleh akses
        $this->actingAs($this->adminUser())
            ->get(route('import.index'))
            ->assertStatus(403);
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
        // Controller menggunakan Excel::raw() + response() langsung (bukan Excel::download())
        // agar kompatibel dengan Windows/Laragon — assert header response, bukan ExcelFake
        $response = $this->actingAs($this->ownerUser())
            ->get(route('import.template'));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="template-import-murid.xlsx"');

        // Verifikasi magic bytes xlsx (PK zip header: 50 4B 03 04)
        $this->assertStringStartsWith("\x50\x4B\x03\x04", $response->content());
    }

    public function test_validate_rejects_non_xlsx_file(): void
    {
        $file = UploadedFile::fake()->create('data.csv', 100, 'text/csv');

        $this->actingAs($this->ownerUser())
            ->post(route('import.validate'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_confirm_redirects_with_error_if_no_session(): void
    {
        $this->actingAs($this->ownerUser())
            ->post(route('import.confirm'))
            ->assertRedirect(route('import.index'))
            ->assertSessionHas('error');
    }

    public function test_cancel_clears_session_and_redirects(): void
    {
        $this->actingAs($this->ownerUser())
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

    public function test_template_berisi_kolom_kode_ruangan(): void
    {
        $owner = $this->ownerUser();

        $response = $this->actingAs($owner)->get(route('import.template'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Pastikan response adalah binary XLSX (magic bytes PK zip header)
        $this->assertEquals("\x50\x4B\x03\x04", substr($response->getContent(), 0, 4));

        // Tulis ke temp file dan baca headernya
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_') . '.xlsx';
        file_put_contents($tmpFile, $response->getContent());
        $rows = \Maatwebsite\Excel\Facades\Excel::toArray([], $tmpFile)[0] ?? [];
        unlink($tmpFile);

        $headers = $rows[0] ?? [];
        $this->assertContains('kode_ruangan', $headers, 'Template harus punya kolom kode_ruangan');
    }

    public function test_confirm_buat_schedules_di_db(): void
    {
        $owner   = $this->ownerUser();
        $piano   = \App\Models\Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        $package = \App\Models\Package::create([
            'code' => 'REG-PIANO', 'instrument_id' => $piano->id,
            'class_type' => 'REGULER', 'grade' => 'Basic',
            'duration_min' => 30, 'price_per_month' => 340000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher = \App\Models\Teacher::create(['code' => 'TCH-ADI', 'name' => 'Adi', 'phone' => '081', 'is_active' => true]);

        session(['import_preview' => [
            'valid' => [
                [
                    'row'  => 2,
                    'data' => [
                        'full_name' => 'Budi Test', 'gender' => 'L', 'status' => 'Aktif',
                        'package_id' => $package->id, 'assigned_teacher_id' => $teacher->id,
                        'room_id' => null, 'preferred_day' => 'Senin', 'preferred_time' => '14:00',
                        'active_since' => '2026-01-15',
                        '_has_warning' => false, '_warning_message' => null,
                        'nickname' => null, 'birth_date' => null, 'phone' => null,
                        'email' => null, 'address' => null, 'notes' => null,
                        'parent_name' => null, 'parent_phone' => null, 'parent_email' => null,
                        'parent_relationship' => null,
                    ],
                ],
            ],
            'overwrite' => [],
            'errors'    => [],
        ]]);

        $this->actingAs($owner)->post(route('import.confirm'));

        $this->assertDatabaseHas('schedules', [
            'day_of_week' => 1,
            'start_time'  => '14:00:00',
            'end_time'    => '14:30:00',
            'is_active'   => true,
        ]);
    }

    public function test_confirm_buat_status_history_migrasi(): void
    {
        $owner   = $this->ownerUser();
        $piano   = \App\Models\Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        $package = \App\Models\Package::create([
            'code' => 'REG-PIANO', 'instrument_id' => $piano->id,
            'class_type' => 'REGULER', 'grade' => 'Basic',
            'duration_min' => 30, 'price_per_month' => 340000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher = \App\Models\Teacher::create(['code' => 'TCH-ADI', 'name' => 'Adi', 'phone' => '081', 'is_active' => true]);

        session(['import_preview' => [
            'valid' => [
                [
                    'row'  => 2,
                    'data' => [
                        'full_name' => 'Sejarah Test', 'gender' => 'L', 'status' => 'Aktif',
                        'package_id' => $package->id, 'assigned_teacher_id' => $teacher->id,
                        'room_id' => null, 'preferred_day' => 'Selasa', 'preferred_time' => '09:00',
                        'active_since' => null,
                        '_has_warning' => false, '_warning_message' => null,
                        'nickname' => null, 'birth_date' => null, 'phone' => null,
                        'email' => null, 'address' => null, 'notes' => null,
                        'parent_name' => null, 'parent_phone' => null, 'parent_email' => null,
                        'parent_relationship' => null,
                    ],
                ],
            ],
            'overwrite' => [],
            'errors'    => [],
        ]]);

        $this->actingAs($owner)->post(route('import.confirm'));

        $student = \App\Models\Student::where('full_name', 'Sejarah Test')->first();
        $this->assertDatabaseHas('student_status_histories', [
            'student_id'    => $student->id,
            'from_status'   => null,
            'reason'        => 'migrasi',
            'skipped_trial' => true,
            'to_status'     => 'Aktif',
        ]);
    }
}
