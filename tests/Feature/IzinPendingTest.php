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
        // Tidak ada sesi pengganti yang dibuat — masih 1 sesi di DB
        $this->assertDatabaseCount('class_sessions', 1);
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
            ['tanggal' => '2026-06-10', 'jam' => '09:00', 'catatan' => 'Murid bilang bisa Rabu']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString(
            '[SARAN GURU: 2026-06-10 09:00',
            \App\Models\ClassSession::find($session->id)->notes
        );
    }

    /** @test */
    public function admin_dapat_isi_slot_dengan_murid_lain(): void
    {
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = \App\Models\User::factory()->create();
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

        // sessionSudahAda sudah punya replacement → tidak muncul di board
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
}
