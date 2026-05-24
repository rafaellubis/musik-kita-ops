<?php

namespace Tests\Feature\Admin;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pastikan role Admin tersedia untuk actingAs
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
    }

    /** Helper: buat ClassSession dengan relasi enrollment+package siap pakai. */
    private function makeSession(array $override = []): ClassSession
    {
        $teacher    = Teacher::factory()->create(['name' => 'Guru Test', 'is_active' => true]);
        $student    = Student::factory()->create();
        $package    = Package::factory()->create(['duration_min' => 30, 'is_active' => true]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        return ClassSession::factory()->create(array_merge([
            'teacher_id'    => $teacher->id,
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
            'session_date'  => '2026-05-20',
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => ClassSession::STATUS_IZIN_RESCHEDULE,
        ], $override));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        return $user;
    }

    /** @test */
    public function happy_path_buat_sesi_pengganti_berhasil(): void
    {
        $session = $this->makeSession();

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-05',
                'replacement_time' => '14:00',
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('class_sessions', [
            'student_id'    => $session->student_id,
            'enrollment_id' => $session->enrollment_id,
            'teacher_id'    => $session->teacher_id,
            'session_date'  => '2026-06-05',
            'start_time'    => '14:00:00',
            'end_time'      => '14:30:00',
            'schedule_id'   => null,
            'status'        => 'SCHEDULED',
        ]);
    }

    /** @test */
    public function konflik_guru_return_422_dengan_pesan_guru(): void
    {
        $session = $this->makeSession();

        // Sesi lain di waktu yang sama dengan guru yang sama
        ClassSession::factory()->create([
            'teacher_id'   => $session->teacher_id,
            'session_date' => '2026-06-05',
            'start_time'   => '14:00:00',
            'end_time'     => '14:30:00',
            'status'       => ClassSession::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-05',
                'replacement_time' => '14:00',
            ]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Guru', $response->json('message'));
    }

    /** @test */
    public function konflik_ruangan_return_422_dengan_pesan_ruangan(): void
    {
        $session = $this->makeSession();
        $room    = Room::factory()->create(['code' => 'R1', 'is_active' => true]);

        ClassSession::factory()->create([
            'room_id'      => $room->id,
            'session_date' => '2026-06-05',
            'start_time'   => '14:10:00',  // overlap sebagian
            'end_time'     => '14:40:00',
            'status'       => ClassSession::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'              => 'IZIN_RESCHEDULE',
                'replacement_date'    => '2026-06-05',
                'replacement_time'    => '14:00',
                'replacement_room_id' => $room->id,
            ]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Ruangan', $response->json('message'));
    }

    /** @test */
    public function ruangan_null_tidak_cek_konflik_ruangan_berhasil(): void
    {
        $session = $this->makeSession();

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-05',
                'replacement_time' => '14:00',
                // replacement_room_id tidak dikirim → null
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    /** @test */
    public function tanggal_bulan_depan_berhasil(): void
    {
        $session = $this->makeSession();

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-07-15',
                'replacement_time' => '09:00',
            ]
        );

        $response->assertOk();
        $this->assertDatabaseHas('class_sessions', [
            'session_date' => '2026-07-15',
            'status'       => 'SCHEDULED',
        ]);
    }

    /** @test */
    public function replacement_date_dan_time_wajib_saat_izin_reschedule(): void
    {
        $session = $this->makeSession();

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            ['status' => 'IZIN_RESCHEDULE']
            // tidak ada replacement_date dan replacement_time
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['replacement_date', 'replacement_time']);
    }

    /** @test */
    public function sesi_pengganti_schedule_id_null_dan_status_scheduled(): void
    {
        $session = $this->makeSession();

        $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-10',
                'replacement_time' => '11:00',
            ]
        );

        $replacement = ClassSession::where('session_date', '2026-06-10')
            ->where('student_id', $session->student_id)
            ->first();

        $this->assertNotNull($replacement);
        $this->assertNull($replacement->schedule_id);
        $this->assertEquals('SCHEDULED', $replacement->status);
    }

    /** @test */
    public function notes_sesi_asli_terupdate_dengan_referensi_replacement(): void
    {
        $session = $this->makeSession();

        $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-10',
                'replacement_time' => '11:00',
            ]
        );

        $session->refresh();
        $this->assertStringContainsString('2026-06-10', $session->notes);
        $this->assertStringContainsString('11:00', $session->notes);
    }

    /** @test */
    public function gagal_reschedule_sesi_yang_sudah_punya_pengganti(): void
    {
        // Skenario: sesi asli sudah di-reschedule ke tgl 30, admin klik "ubah" lalu coba
        // reschedule lagi ke tgl 31 — harus ditolak (tidak boleh ada 2 replacement)
        $original = $this->makeSession();

        // Replacement pertama sudah ada
        ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'split_part'        => null,
            'session_date'      => '2026-06-30',
            'status'            => ClassSession::STATUS_SCHEDULED,
            'enrollment_id'     => $original->enrollment_id,
            'student_id'        => $original->student_id,
            'teacher_id'        => $original->teacher_id,
        ]);

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $original),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-07-31',
                'replacement_time' => '10:00',
            ]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('pengganti', strtolower($response->json('message')));
        $this->assertDatabaseMissing('class_sessions', ['session_date' => '2026-07-31']);
    }

    /** @test */
    public function sesi_pengganti_boleh_di_reschedule_lagi(): void
    {
        // Chain A → B → C diizinkan (replacement bisa di-reschedule)
        $original    = $this->makeSession(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);
        $replacement = $this->makeSession([
            'origin_session_id' => $original->id,
            'split_part'        => null,
            'session_date'      => '2026-06-30',
            'status'            => ClassSession::STATUS_IZIN_RESCHEDULE,
        ]);

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $replacement),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-07-15',
                'replacement_time' => '10:00',
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('class_sessions', [
            'session_date'      => '2026-07-15',
            'origin_session_id' => $replacement->id,
            'status'            => 'SCHEDULED',
        ]);
    }
}
