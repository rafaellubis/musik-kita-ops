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
