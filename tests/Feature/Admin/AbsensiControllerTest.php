<?php

namespace Tests\Feature\Admin;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test untuk AbsensiController (M04 — Absensi Harian).
 * Task 1: scaffold dasar — route terdaftar, halaman bisa diakses.
 */
class AbsensiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat role yang dibutuhkan — pola sama dengan test lain di project ini
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * Helper: buat ClassSession lengkap dengan seluruh chain relasi.
     * Dipakai di semua test agar tidak ada duplikasi setup.
     */
    private function createTestSession(array $overrides = []): ClassSession
    {
        $teacher    = Teacher::factory()->create(['is_active' => true]);
        $student    = Student::factory()->create();
        $room       = Room::factory()->create(['is_active' => true]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule = Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'room_id'       => $room->id,
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
        ]);

        return ClassSession::factory()->create(array_merge([
            'schedule_id'   => $schedule->id,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'session_date'  => today(),
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ], $overrides));
    }

    public function test_halaman_absensi_dapat_diakses_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.absensi.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.absensi.index');
    }

    public function test_halaman_absensi_dapat_diakses_owner(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('admin.absensi.index'));

        $response->assertStatus(200);
    }

    public function test_halaman_absensi_tidak_bisa_diakses_guest(): void
    {
        $response = $this->get(route('admin.absensi.index'));

        // Guest diredirect ke halaman login
        $response->assertRedirect(route('login'));
    }

    public function test_halaman_absensi_tampil_sesi_hari_ini(): void
    {
        $session = $this->createTestSession(['session_date' => today()]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.absensi.index'));

        $response->assertStatus(200);
        $response->assertViewHas('sessions', fn ($sessions) =>
            $sessions->contains('id', $session->id)
        );
    }

    public function test_absensi_filter_per_tanggal(): void
    {
        $sessionHariIni = $this->createTestSession(['session_date' => today()]);
        $sessionKemarin = $this->createTestSession(['session_date' => today()->subDay()]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.absensi.index', ['date' => today()->subDay()->toDateString()])
        );

        $response->assertViewHas('sessions', function ($sessions) use ($sessionKemarin, $sessionHariIni) {
            return $sessions->contains('id', $sessionKemarin->id)
                && ! $sessions->contains('id', $sessionHariIni->id);
        });
    }

    // ----------------------------------------------------------------
    // Task 4 — UpdateAbsensiRequest + update()
    // ----------------------------------------------------------------

    public function test_input_status_hadir_berhasil(): void
    {
        $session = $this->createTestSession();

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.absensi.update', $session), ['status' => 'HADIR']);

        $response->assertOk()->assertJson(['success' => true, 'status' => 'HADIR']);
        $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'HADIR']);
    }

    public function test_input_status_hangus_berhasil(): void
    {
        $session = $this->createTestSession();

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.absensi.update', $session), ['status' => 'HANGUS']);

        $response->assertOk()->assertJson(['success' => true, 'status' => 'HANGUS']);
        $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'HANGUS']);
    }

    public function test_status_libur_tidak_bisa_diubah(): void
    {
        $session = $this->createTestSession(['status' => 'LIBUR']);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.absensi.update', $session), ['status' => 'HADIR']);

        $response->assertForbidden();
        $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'LIBUR']);
    }

    public function test_edit_ulang_status_yang_sudah_diinput(): void
    {
        $session = $this->createTestSession(['status' => 'HADIR']);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.absensi.update', $session), ['status' => 'HANGUS']);

        $response->assertOk()->assertJson(['success' => true, 'status' => 'HANGUS']);
        $this->assertDatabaseHas('class_sessions', ['id' => $session->id, 'status' => 'HANGUS']);
    }

    public function test_status_tidak_valid_ditolak(): void
    {
        $session = $this->createTestSession();

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.absensi.update', $session), ['status' => 'STATUS_TIDAK_ADA']);

        $response->assertUnprocessable(); // 422
    }

    // ----------------------------------------------------------------
    // Task 5 — Validasi TERLAMBAT + DIGANTI
    // ----------------------------------------------------------------

    public function test_input_hadir_terlambat_tanpa_late_minutes_ditolak(): void
    {
        $session = $this->createTestSession();

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.absensi.update', $session), [
                'status' => 'HADIR_TERLAMBAT',
                // late_minutes sengaja tidak dikirim
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['late_minutes']);
    }

    public function test_input_hadir_terlambat_min_1_menit(): void
    {
        $session = $this->createTestSession();

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.absensi.update', $session), [
                'status'       => 'HADIR_TERLAMBAT',
                'late_minutes' => 0,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['late_minutes']);
    }

    public function test_input_diganti_tanpa_guru_pengganti_ditolak(): void
    {
        $session = $this->createTestSession();

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.absensi.update', $session), [
                'status' => 'DIGANTI',
                // substitute_teacher_id sengaja tidak dikirim
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['substitute_teacher_id']);
    }

    public function test_input_hadir_terlambat_berhasil_simpan_late_minutes(): void
    {
        $session = $this->createTestSession();

        $response = $this->actingAs($this->admin)
            ->patchJson(route('admin.absensi.update', $session), [
                'status'       => 'HADIR_TERLAMBAT',
                'late_minutes' => 20,
            ]);

        $response->assertOk()->assertJson(['success' => true, 'late_minutes' => 20]);
        $this->assertDatabaseHas('class_sessions', [
            'id'           => $session->id,
            'status'       => 'HADIR_TERLAMBAT',
            'late_minutes' => 20,
        ]);
    }
}
