<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\HonorCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IzinPendingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(array $attrs = []): ClassSession
    {
        $teacher    = Teacher::factory()->create();
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $package    = Package::factory()->create(['price_per_month' => 400000, 'class_type' => 'REGULER']);
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
            'status'        => 'SCHEDULED',
            'session_date'  => today()->toDateString(),
            'start_time'    => '09:00',
            'end_time'      => '09:30',
        ], $attrs));
    }

    /** @test */
    public function attendance_service_sets_honor_nol_for_izin_pending(): void
    {
        $session = $this->makeSession();
        $service = app(AttendanceService::class);

        $result = $service->recordAttendance($session, ['status' => 'IZIN_PENDING']);

        $this->assertEquals('IZIN_PENDING', $result->status);
        $this->assertEquals('H_IZIN', $result->honor_code);
        $this->assertEquals(0, $result->honor_amount);
    }

    /** @test */
    public function admin_dapat_set_izin_pending_tanpa_isi_tanggal_pengganti(): void
    {
        // Buat role dulu agar assignRole tidak lempar RoleDoesNotExist
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('Admin');
        $session = $this->makeSession();

        $response = $this->actingAs($admin)->patchJson(
            route('absensi.update', $session),
            ['status' => 'IZIN_PENDING']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('class_sessions', [
            'id'           => $session->id,
            'status'       => 'IZIN_PENDING',
            'honor_code'   => 'H_IZIN',
            'honor_amount' => 0,
        ]);
        // Tidak ada sesi pengganti yang dibuat â€” masih 1 sesi di DB
        $this->assertDatabaseCount('class_sessions', 1);
    }

    /** @test */
    public function admin_dapat_set_izin_pending_dengan_catatan(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $admin   = \App\Models\User::factory()->create();
        $admin->assignRole('Admin');
        $session = $this->makeSession();

        $response = $this->actingAs($admin)->patchJson(
            route('absensi.update', $session),
            [
                'status' => 'IZIN_PENDING',
                'notes'  => 'Murid izin sakit, jadwal menyusul',
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('class_sessions', [
            'id'     => $session->id,
            'status' => 'IZIN_PENDING',
            'notes'  => 'Murid izin sakit, jadwal menyusul',
        ]);
    }

    /** @test */
    public function honor_calculation_excludes_izin_pending_sessions(): void
    {
        // Buat user agar FK created_by di teacher_honor_slips tidak error
        $owner   = User::factory()->create();
        $teacher = Teacher::factory()->create();
        $bulan   = now()->month;
        $tahun   = now()->year;

        $package  = Package::factory()->create(['price_per_month' => 400000, 'class_type' => 'REGULER']);
        $student1 = Student::factory()->create(['status' => 'Aktif']);
        $enrollment1 = Enrollment::factory()->create([
            'student_id' => $student1->id, 'teacher_id' => $teacher->id,
            'package_id' => $package->id, 'status' => 'ACTIVE',
        ]);
        ClassSession::factory()->create([
            'teacher_id'    => $teacher->id,
            'student_id'    => $student1->id,
            'enrollment_id' => $enrollment1->id,
            'status'        => 'HADIR',
            'honor_code'    => 'H_REG',
            'honor_amount'  => 50000,
            'session_date'  => now()->startOfMonth()->toDateString(),
        ]);

        $student2 = Student::factory()->create(['status' => 'Aktif']);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student2->id, 'teacher_id' => $teacher->id,
            'package_id' => $package->id, 'status' => 'ACTIVE',
        ]);
        ClassSession::factory()->create([
            'teacher_id'    => $teacher->id,
            'student_id'    => $student2->id,
            'enrollment_id' => $enrollment2->id,
            'status'        => 'IZIN_PENDING',
            'honor_code'    => 'H_IZIN',
            'honor_amount'  => 0,
            'session_date'  => now()->startOfMonth()->toDateString(),
        ]);

        $service = app(HonorCalculationService::class);
        $service->calculateForTeacher($teacher, $tahun, $bulan, $owner->id);

        $slip = \App\Models\HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', $bulan)
            ->where('year', $tahun)
            ->first();
        $this->assertNotNull($slip, 'Slip honor harus terbuat setelah kalkulasi.');
        $this->assertEquals(50000, $slip->base_honor);
    }

    /** @test */
    public function guru_hanya_lihat_sesi_pending_miliknya(): void
    {
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
        $guruUser = \App\Models\User::factory()->create(['email_verified_at' => now()]);
        $guruUser->assignRole('Guru');
        // Relasi User hasOne Teacher via teacher_id di tabel teachers
        $teacher = Teacher::factory()->create(['user_id' => $guruUser->id]);

        $teacher2 = Teacher::factory()->create();

        $milik = $this->makeSession([
            'status' => 'IZIN_PENDING', 'honor_code' => 'H_IZIN', 'honor_amount' => 0,
            'teacher_id' => $teacher->id,
        ]);
        $bukan = $this->makeSession([
            'status' => 'IZIN_PENDING', 'honor_code' => 'H_IZIN', 'honor_amount' => 0,
            'teacher_id' => $teacher2->id,
        ]);

        $response = $this->actingAs($guruUser)->get(route('guru.sesi-pending.index'));

        $response->assertOk()
                 ->assertSee($milik->student->full_name)
                 ->assertDontSee($bukan->student->full_name);
    }

    /** @test */
    public function guru_bisa_suggest_tanggal(): void
    {
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
        $guruUser = \App\Models\User::factory()->create(['email_verified_at' => now()]);
        $guruUser->assignRole('Guru');
        // Relasi User hasOne Teacher via teacher_id di tabel teachers
        $teacher = Teacher::factory()->create(['user_id' => $guruUser->id]);

        $session = $this->makeSession([
            'status'       => 'IZIN_PENDING',
            'honor_code'   => 'H_IZIN',
            'honor_amount' => 0,
            'teacher_id'   => $teacher->id,
        ]);

        $response = $this->actingAs($guruUser)->postJson(
            route('guru.sesi-pending.suggest', $session),
            ['tanggal' => today()->addDays(3)->toDateString(), 'jam' => '09:00', 'catatan' => 'Murid bilang bisa Rabu']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString(
            '[SARAN GURU: ' . today()->addDays(3)->toDateString() . ' 09:00',
            \App\Models\ClassSession::find($session->id)->notes
        );
    }

    /** @test */
    public function guru_suggest_kedua_kali_menambah_catatan_baru(): void
    {
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
        $guruUser = User::factory()->create(['email_verified_at' => now()]);
        $guruUser->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $guruUser->id]);

        $session = $this->makeSession([
            'status'       => 'IZIN_PENDING',
            'honor_code'   => 'H_IZIN',
            'honor_amount' => 0,
            'teacher_id'   => $teacher->id,
        ]);

        $tanggal1 = today()->addDays(3)->toDateString();
        $tanggal2 = today()->addDays(7)->toDateString();

        $this->actingAs($guruUser)->postJson(
            route('guru.sesi-pending.suggest', $session),
            ['tanggal' => $tanggal1, 'jam' => '09:00', 'catatan' => 'Saran pertama']
        )->assertOk();

        $response = $this->actingAs($guruUser)->postJson(
            route('guru.sesi-pending.suggest', $session),
            ['tanggal' => $tanggal2, 'jam' => '10:00', 'catatan' => 'Saran kedua']
        );

        $response->assertOk()
            ->assertJson([
                'success'          => true,
                'suggestion_count' => 2,
            ])
            ->assertJsonPath('latest.tanggal', $tanggal2)
            ->assertJsonPath('latest.jam', '10:00');

        $fresh = ClassSession::find($session->id);
        $this->assertStringContainsString("[SARAN GURU: {$tanggal1} 09:00", $fresh->notes);
        $this->assertStringContainsString("[SARAN GURU: {$tanggal2} 10:00", $fresh->notes);
        $this->assertSame(2, $fresh->teacherSuggestionCount());
        $this->assertSame($tanggal2, $fresh->latestTeacherSuggestion()['tanggal']);
    }

    /** @test */
    public function admin_open_slots_menampilkan_saran_terbaru_dan_counter(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $tanggal1 = today()->addDays(3)->toDateString();
        $tanggal2 = today()->addDays(7)->toDateString();

        $session = $this->makeSession([
            'status'       => 'IZIN_PENDING',
            'honor_code'   => 'H_IZIN',
            'honor_amount' => 0,
            'notes'        => "Catatan admin\n[SARAN GURU: {$tanggal1} 09:00 â€” Saran pertama]\n[SARAN GURU: {$tanggal2} 10:00 â€” Saran kedua]",
        ]);

        $response = $this->actingAs($admin)->get(route('absensi.open-slots'));

        $response->assertOk()
            ->assertSee("{$tanggal2} 10:00 â€” Saran kedua")
            ->assertSee('Saran ke-2')
            ->assertSee('Catatan admin')
            ->assertSee('#1')
            ->assertSee("{$tanggal1} 09:00 â€” Saran pertama");
    }

    /** @test */
    public function admin_dapat_isi_slot_dengan_murid_lain(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        // Sesi IZIN_PENDING milik Budi
        $sessionBudi = $this->makeSession([
            'status'       => 'IZIN_PENDING',
            'honor_code'   => 'H_IZIN',
            'honor_amount' => 0,
            'session_date' => today()->toDateString(),
            'start_time'   => '09:00',
            'end_time'     => '09:30',
        ]);

        // Enrollment murid lain (Cici) yang akan mengisi slot
        $studentCici = \App\Models\Student::factory()->create(['status' => 'Aktif']);
        $package     = \App\Models\Package::factory()->create(['price_per_month' => 400000, 'class_type' => 'REGULER']);
        $enrollmentCici = \App\Models\Enrollment::factory()->create([
            'student_id' => $studentCici->id,
            'teacher_id' => $sessionBudi->teacher_id, // guru yang sama
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('absensi.open-slots.assign', $sessionBudi),
            ['enrollment_id' => $enrollmentCici->id]
        );

        $response->assertOk()->assertJson(['success' => true]);

        // Sesi Budi masih IZIN_PENDING (belum selesai)
        $this->assertDatabaseHas('class_sessions', [
            'id'     => $sessionBudi->id,
            'status' => 'IZIN_PENDING',
        ]);

        // Sesi baru untuk Cici dibuat di slot yang sama
        $this->assertDatabaseHas('class_sessions', [
            'student_id'    => $studentCici->id,
            'enrollment_id' => $enrollmentCici->id,
            'teacher_id'    => $sessionBudi->teacher_id,
            'session_date'  => today()->toDateString(),
            'start_time'    => '09:00:00',
        ]);
    }

    /** @test */
    public function admin_dapat_batalkan_izin_pending_dari_open_slot_board(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin   = User::factory()->create();
        $admin->assignRole('Admin');
        $session = $this->makeSession([
            'status'       => 'IZIN_PENDING',
            'honor_code'   => 'H_IZIN',
            'honor_amount' => 0,
            'notes'        => 'Murid izin sakit',
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('absensi.open-slots.cancel', $session)
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('class_sessions', [
            'id'           => $session->id,
            'status'       => 'SCHEDULED',
            'notes'        => null,
            'honor_code'   => null,
            'honor_amount' => null,
        ]);
    }

    /** @test */
    public function batalkan_izin_pending_ditolak_jika_sudah_punya_replacement(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin   = User::factory()->create();
        $admin->assignRole('Admin');
        $session = $this->makeSession([
            'status'       => 'IZIN_PENDING',
            'honor_code'   => 'H_IZIN',
            'honor_amount' => 0,
        ]);

        ClassSession::factory()->create([
            'origin_session_id' => $session->id,
            'status'            => 'SCHEDULED',
            'teacher_id'        => $session->teacher_id,
            'student_id'        => $session->student_id,
            'enrollment_id'     => $session->enrollment_id,
            'session_date'      => $session->session_date,
            'start_time'        => $session->start_time,
            'end_time'          => $session->end_time,
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('absensi.open-slots.cancel', $session)
        );

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseHas('class_sessions', [
            'id'     => $session->id,
            'status' => 'IZIN_PENDING',
        ]);
    }

    /** @test */
    public function test_update_menolak_ubah_izin_reschedule_ke_izin_pending_jika_sudah_ada_sesi_pengganti(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin     = \App\Models\User::factory()->create();
        $admin->assignRole('Admin');

        $teacher    = \App\Models\Teacher::factory()->create(['is_active' => true]);
        $room       = \App\Models\Room::factory()->create(['capacity' => 1, 'is_active' => true]);
        $package    = \App\Models\Package::factory()->create(['class_type' => 'REGULER', 'duration_min' => 30, 'price_per_month' => 370000, 'is_active' => true]);
        $student    = \App\Models\Student::factory()->create(['status' => 'Aktif']);
        $enrollment = \App\Models\Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        // Sesi asli sudah IZIN_RESCHEDULE
        $sesiAsli = \App\Models\ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'session_date'  => '2026-07-07',
            'start_time'    => '15:00:00',
            'end_time'      => '15:30:00',
            'status'        => 'IZIN_RESCHEDULE',
            'honor_code'    => null,
            'honor_amount'  => 0,
        ]);

        // Sesi pengganti sudah ada (non-split)
        \App\Models\ClassSession::factory()->create([
            'enrollment_id'     => $enrollment->id,
            'student_id'        => $student->id,
            'teacher_id'        => $teacher->id,
            'room_id'           => $room->id,
            'session_date'      => '2026-07-14',
            'start_time'        => '15:00:00',
            'end_time'          => '15:30:00',
            'status'            => 'SCHEDULED',
            'origin_session_id' => $sesiAsli->id,
            'split_part'        => null,
        ]);

        // Coba ubah sesi asli dari IZIN_RESCHEDULE â†’ IZIN_PENDING â€” harus ditolak
        $response = $this->actingAs($admin)
            ->patchJson(route('absensi.update', $sesiAsli), [
                'status' => 'IZIN_PENDING',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString(
            'pengganti',
            $response->json('message')
        );

        // Status sesi asli tidak berubah
        $this->assertEquals('IZIN_RESCHEDULE', $sesiAsli->fresh()->status);
    }

    /** @test */
    public function open_slot_board_hanya_tampilkan_izin_pending_tanpa_replacement(): void
    {
        $admin = \App\Models\User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->assignRole('Admin');

        $sessionPending  = $this->makeSession([
            'status' => 'IZIN_PENDING', 'honor_code' => 'H_IZIN', 'honor_amount' => 0,
        ]);
        $sessionSudahAda = $this->makeSession([
            'status' => 'IZIN_PENDING', 'honor_code' => 'H_IZIN', 'honor_amount' => 0,
        ]);

        // sessionSudahAda sudah punya replacement â†’ tidak muncul di board
        \App\Models\ClassSession::factory()->create([
            'origin_session_id' => $sessionSudahAda->id,
            'status'            => 'SCHEDULED',
            'teacher_id'        => $sessionSudahAda->teacher_id,
            'student_id'        => $sessionSudahAda->student_id,
            'enrollment_id'     => $sessionSudahAda->enrollment_id,
            'session_date'      => today()->addDays(3)->toDateString(),
            'start_time'        => '09:00',
            'end_time'          => '09:30',
        ]);

        $response = $this->actingAs($admin)->getJson(route('absensi.open-slots'));

        $response->assertOk();
        $ids = collect($response->json('slots'))->pluck('id');
        $this->assertTrue($ids->contains($sessionPending->id));
        $this->assertFalse($ids->contains($sessionSudahAda->id));
    }
    /** @test */
    public function finalize_pending_as_video_sets_h_video_honor(): void
    {
        $session = $this->makeSession([
            'status'       => 'IZIN_PENDING',
            'honor_code'   => 'H_IZIN',
            'honor_amount' => 0,
            'notes'        => 'Murid izin, jadwal menyusul',
        ]);

        $service = app(AttendanceService::class);
        $result  = $service->finalizePendingAsVideo($session, 'Link video dikirim via WA');

        $this->assertEquals('IZIN_VIDEO', $result->status);
        $this->assertEquals('H_VIDEO', $result->honor_code);
        $this->assertEquals(50000, $result->honor_amount); // 400k * 50% / 4
        $this->assertStringContainsString('[VIDEO via Open Slot]', $result->notes);
        $this->assertStringContainsString('Link video dikirim via WA', $result->notes);
        $this->assertStringContainsString('Murid izin, jadwal menyusul', $result->notes);
    }

    /** @test */
    public function finalize_pending_as_video_tanpa_catatan_pertahankan_notes_lama(): void
    {
        $session = $this->makeSession([
            'status' => 'IZIN_PENDING',
            'notes'  => 'Catatan lama saja',
        ]);

        $result = app(AttendanceService::class)->finalizePendingAsVideo($session, null);

        $this->assertEquals('IZIN_VIDEO', $result->status);
        $this->assertEquals('Catatan lama saja', $result->notes);
    }

    /** @test */
    public function finalize_pending_as_video_ditolak_jika_bukan_izin_pending(): void
    {
        $session = $this->makeSession(['status' => 'SCHEDULED']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sesi bukan IZIN_PENDING.');

        app(AttendanceService::class)->finalizePendingAsVideo($session);
    }

    /** @test */
    public function finalize_pending_as_video_ditolak_jika_sudah_punya_replacement(): void
    {
        $session = $this->makeSession(['status' => 'IZIN_PENDING']);

        ClassSession::factory()->create([
            'origin_session_id' => $session->id,
            'status'            => 'SCHEDULED',
            'teacher_id'        => $session->teacher_id,
            'student_id'        => $session->student_id,
            'enrollment_id'     => $session->enrollment_id,
            'session_date'      => $session->session_date,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sudah punya sesi pengganti');

        app(AttendanceService::class)->finalizePendingAsVideo($session);
    }
    /** @test */
    public function admin_dapat_convert_izin_pending_ke_video_dari_open_slot_board(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin   = User::factory()->create();
        $admin->assignRole('Admin');
        $session = $this->makeSession([
            'status'       => 'IZIN_PENDING',
            'honor_code'   => 'H_IZIN',
            'honor_amount' => 0,
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('absensi.open-slots.video', $session),
            ['notes' => 'Murid minta video pengganti']
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('class_sessions', [
            'id'           => $session->id,
            'status'       => 'IZIN_VIDEO',
            'honor_code'   => 'H_VIDEO',
            'honor_amount' => 50000,
        ]);
    /** @test */
    public function convert_video_ditolak_jika_bukan_izin_pending(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin   = User::factory()->create();
        $admin->assignRole('Admin');
        $session = $this->makeSession(['status' => 'SCHEDULED']);

        $response = $this->actingAs($admin)->postJson(
            route('absensi.open-slots.video', $session)
        );

        $response->assertStatus(422);
    }

    /** @test */
    public function convert_video_ditolak_jika_sudah_punya_replacement(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin   = User::factory()->create();
        $admin->assignRole('Admin');
        $session = $this->makeSession(['status' => 'IZIN_PENDING']);

        ClassSession::factory()->create([
            'origin_session_id' => $session->id,
            'status'            => 'SCHEDULED',
            'teacher_id'        => $session->teacher_id,
            'student_id'        => $session->student_id,
            'enrollment_id'     => $session->enrollment_id,
            'session_date'      => $session->session_date,
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('absensi.open-slots.video', $session)
        );

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function convert_video_ditolak_untuk_role_guru(): void
    {
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
        $guru    = User::factory()->create();
        $guru->assignRole('Guru');
        $session = $this->makeSession(['status' => 'IZIN_PENDING']);

        $response = $this->actingAs($guru)->postJson(
            route('absensi.open-slots.video', $session)
        );

        $response->assertForbidden();
    }

    /** @test */
    public function sesi_izin_video_tidak_muncul_di_open_slot_board(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin   = User::factory()->create();
        $admin->assignRole('Admin');
        $session = $this->makeSession([
            'status'       => 'IZIN_PENDING',
            'honor_code'   => 'H_IZIN',
            'honor_amount' => 0,
        ]);

        $this->actingAs($admin)->postJson(
            route('absensi.open-slots.video', $session)
        )->assertOk();

        $response = $this->actingAs($admin)->getJson(route('absensi.open-slots'));

        $response->assertOk();
        $ids = collect($response->json('slots'))->pluck('id');
        $this->assertFalse($ids->contains($session->id));
    }

    /** @test */
    public function convert_video_update_last_session_at_murid(): void
    {
        $session = $this->makeSession([
            'status'       => 'IZIN_PENDING',
            'session_date' => '2026-06-01',
            'start_time'   => '10:00:00',
        ]);
        $student = $session->student;
        $student->update(['last_session_at' => null]);

        app(AttendanceService::class)->finalizePendingAsVideo($session);

        $student->refresh();
        $this->assertNotNull($student->last_session_at);
        $this->assertEquals('2026-06-01', $student->last_session_at->toDateString());
    }
