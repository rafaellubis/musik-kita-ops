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
    public function slot_izin_reschedule_murid_lain_tidak_memblok_reschedule_ke_jam_itu(): void
    {
        // Skenario: murid A sudah reschedule dari 15:00 → 17:30 (sesi asli tetap IZIN_RESCHEDULE di 15:00)
        // Murid B di 15:30 boleh reschedule ke 15:00 di slot yang sama
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $room    = Room::factory()->create(['is_active' => true]);
        $package = Package::factory()->create(['duration_min' => 30]);

        $studentA = Student::factory()->create();
        $enrollA  = Enrollment::factory()->create([
            'student_id' => $studentA->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        $originalA = ClassSession::factory()->create([
            'teacher_id'    => $teacher->id,
            'student_id'    => $studentA->id,
            'enrollment_id' => $enrollA->id,
            'room_id'       => $room->id,
            'session_date'  => '2026-06-02',
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'status'        => ClassSession::STATUS_IZIN_RESCHEDULE,
        ]);

        ClassSession::factory()->create([
            'origin_session_id' => $originalA->id,
            'teacher_id'        => $teacher->id,
            'student_id'        => $studentA->id,
            'enrollment_id'     => $enrollA->id,
            'room_id'           => $room->id,
            'session_date'      => '2026-06-02',
            'start_time'        => '17:30:00',
            'end_time'          => '18:00:00',
            'status'            => ClassSession::STATUS_SCHEDULED,
        ]);

        $studentB = Student::factory()->create();
        $enrollB  = Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        $sessionB = ClassSession::factory()->create([
            'teacher_id'    => $teacher->id,
            'student_id'    => $studentB->id,
            'enrollment_id' => $enrollB->id,
            'room_id'       => $room->id,
            'session_date'  => '2026-06-02',
            'start_time'    => '15:30:00',
            'end_time'      => '16:00:00',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $sessionB),
            [
                'status'              => 'IZIN_RESCHEDULE',
                'replacement_date'    => '2026-06-02',
                'replacement_time'    => '15:00',
                'replacement_room_id' => $room->id,
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('class_sessions', [
            'student_id'        => $studentB->id,
            'session_date'      => '2026-06-02',
            'start_time'        => '15:00:00',
            'origin_session_id' => $sessionB->id,
            'status'            => 'SCHEDULED',
        ]);
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

    /** Helper: buat sesi DUO dengan enrollment+package DUO. */
    private function makeDuoSession(array $override = []): ClassSession
    {
        $teacher = $override['teacher'] ?? Teacher::factory()->create(['name' => 'Guru DUO', 'is_active' => true]);
        unset($override['teacher']);

        $student = Student::factory()->create();
        $package = Package::factory()->create([
            'class_type'   => 'DUO',
            'duration_min' => 30,
            'is_active'    => true,
        ]);
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
            'session_date'  => '2026-06-02',
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => ClassSession::STATUS_IZIN_RESCHEDULE,
        ], $override));
    }

    /** @test */
    public function duo_reschedule_ke_slot_pasangan_duo_berhasil(): void
    {
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $room    = Room::factory()->create(['is_active' => true]);

        $sessionA = $this->makeDuoSession([
            'teacher'      => $teacher,
            'room_id'      => $room->id,
            'session_date' => '2026-06-02',
            'start_time'   => '09:00:00',
            'end_time'     => '09:30:00',
        ]);

        // Pasangan DUO sudah punya sesi di slot target
        $partnerPackage = Package::factory()->create(['class_type' => 'DUO', 'duration_min' => 30]);
        $partnerEnroll  = Enrollment::factory()->create([
            'student_id' => Student::factory()->create()->id,
            'teacher_id' => $teacher->id,
            'package_id' => $partnerPackage->id,
            'status'     => 'ACTIVE',
        ]);
        ClassSession::factory()->create([
            'teacher_id'    => $teacher->id,
            'student_id'    => $partnerEnroll->student_id,
            'enrollment_id' => $partnerEnroll->id,
            'room_id'       => $room->id,
            'session_date'  => '2026-06-05',
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $sessionA),
            [
                'status'              => 'IZIN_RESCHEDULE',
                'replacement_date'    => '2026-06-05',
                'replacement_time'    => '10:00',
                'replacement_room_id' => $room->id,
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('class_sessions', [
            'student_id'        => $sessionA->student_id,
            'session_date'      => '2026-06-05',
            'start_time'        => '10:00:00',
            'origin_session_id' => $sessionA->id,
            'status'            => 'SCHEDULED',
        ]);
    }

    /** @test */
    public function duo_reschedule_ke_slot_reguler_gagal(): void
    {
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $session = $this->makeDuoSession(['teacher' => $teacher]);

        $regularPackage = Package::factory()->create(['class_type' => 'REGULER', 'duration_min' => 30]);
        $regularEnroll  = Enrollment::factory()->create([
            'student_id' => Student::factory()->create()->id,
            'teacher_id' => $teacher->id,
            'package_id' => $regularPackage->id,
            'status'     => 'ACTIVE',
        ]);
        ClassSession::factory()->create([
            'teacher_id'    => $teacher->id,
            'student_id'    => $regularEnroll->student_id,
            'enrollment_id' => $regularEnroll->id,
            'session_date'  => '2026-06-05',
            'start_time'    => '14:00:00',
            'end_time'      => '14:30:00',
            'status'        => ClassSession::STATUS_SCHEDULED,
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
    public function duo_reschedule_ke_slot_duo_penuh_gagal(): void
    {
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $session = $this->makeDuoSession(['teacher' => $teacher]);

        $duoPackage = Package::factory()->create(['class_type' => 'DUO', 'duration_min' => 30]);

        foreach (range(1, 2) as $_) {
            $enroll = Enrollment::factory()->create([
                'student_id' => Student::factory()->create()->id,
                'teacher_id' => $teacher->id,
                'package_id' => $duoPackage->id,
                'status'     => 'ACTIVE',
            ]);
            ClassSession::factory()->create([
                'teacher_id'    => $teacher->id,
                'student_id'    => $enroll->student_id,
                'enrollment_id' => $enroll->id,
                'session_date'  => '2026-06-05',
                'start_time'    => '14:00:00',
                'end_time'      => '14:30:00',
                'status'        => ClassSession::STATUS_SCHEDULED,
            ]);
        }

        $response = $this->actingAs($this->adminUser())->patchJson(
            route('absensi.update', $session),
            [
                'status'           => 'IZIN_RESCHEDULE',
                'replacement_date' => '2026-06-05',
                'replacement_time' => '14:00',
            ]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Slot DUO sudah penuh', $response->json('message'));
    }
}
