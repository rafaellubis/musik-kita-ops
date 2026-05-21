<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StudentLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentCutiTest extends TestCase
{
    use RefreshDatabase;

    private StudentLifecycleService $lifecycle;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $user = User::factory()->create()->assignRole('Admin');
        $this->actingAs($user);

        $this->lifecycle = new StudentLifecycleService(new InvoiceService());
        $this->student   = Student::factory()->create(['status' => 'Aktif']);
    }

    // ===== ajukanCuti() =====

    public function test_ajukan_cuti_menyimpan_cuti_from_dan_cuti_until(): void
    {
        $this->lifecycle->ajukanCuti($this->student, [
            'cuti_from'  => '2026-07-01',
            'cuti_until' => '2026-07-31',
            'reason'     => 'UAS sekolah',
        ]);

        $this->student->refresh();
        $this->assertEquals('Cuti',       $this->student->status);
        $this->assertEquals('2026-07-01', $this->student->cuti_from->format('Y-m-d'));
        $this->assertEquals('2026-07-31', $this->student->cuti_until->format('Y-m-d'));
    }

    public function test_ajukan_cuti_cancel_sesi_scheduled_dalam_periode(): void
    {
        // Buat enrollment dengan relasi valid — FK constraint aktif di SQLite in-memory
        $teacher    = Teacher::factory()->create();
        $package    = Package::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id'     => $this->student->id,
            'package_id'     => $package->id,
            'teacher_id'     => $teacher->id,
            'effective_date' => now()->subMonth()->toDateString(),
            'status'         => 'ACTIVE',
        ]);

        // Sesi di dalam periode cuti — harus di-cancel
        $sesiDalam = ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $this->student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => '2026-07-10',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        // Sesi di luar periode cuti — tidak boleh tersentuh
        $sesiLuar = ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $this->student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => '2026-08-05',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        $this->lifecycle->ajukanCuti($this->student, [
            'cuti_from'  => '2026-07-01',
            'cuti_until' => '2026-07-31',
            'reason'     => 'UAS sekolah',
        ]);

        $this->assertEquals(ClassSession::STATUS_CANCELLED, $sesiDalam->fresh()->status);
        $this->assertEquals(ClassSession::STATUS_SCHEDULED, $sesiLuar->fresh()->status);
    }

    public function test_perpanjang_cuti_tidak_override_cuti_from(): void
    {
        // Setup: murid sudah Cuti dengan cuti_from terdaftar
        $this->student->update([
            'status'     => 'Cuti',
            'cuti_from'  => '2026-07-01',
            'cuti_until' => '2026-07-31',
        ]);

        $this->lifecycle->ajukanCuti($this->student, [
            'cuti_until' => '2026-08-15',
            'reason'     => 'Perpanjang karena sakit',
        ]);

        $this->student->refresh();
        $this->assertEquals('2026-07-01', $this->student->cuti_from->format('Y-m-d'));  // tidak berubah
        $this->assertEquals('2026-08-15', $this->student->cuti_until->format('Y-m-d')); // di-update
    }

    public function test_perpanjang_cuti_melewati_62_hari_ditolak(): void
    {
        $this->student->update([
            'status'     => 'Cuti',
            'cuti_from'  => '2026-07-01',
            'cuti_until' => '2026-07-31',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/maks.*2 bulan/i');

        $this->lifecycle->ajukanCuti($this->student, [
            'cuti_until' => '2026-09-15', // 76 hari dari cuti_from — melebihi batas
            'reason'     => 'Terlalu panjang',
        ]);
    }

    // ===== aktifkanDariCuti() =====

    public function test_akhiri_cuti_sebelum_cuti_until_ditolak(): void
    {
        $this->student->update([
            'status'     => 'Cuti',
            'cuti_from'  => now()->subDays(5)->toDateString(),
            'cuti_until' => now()->addDays(10)->toDateString(), // belum lewat
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/belum selesai/i');

        $this->lifecycle->aktifkanDariCuti($this->student);
    }

    public function test_akhiri_cuti_setelah_cuti_until_berhasil(): void
    {
        $this->student->update([
            'status'     => 'Cuti',
            'cuti_from'  => now()->subDays(30)->toDateString(),
            'cuti_until' => now()->subDay()->toDateString(), // sudah lewat
        ]);

        $result = $this->lifecycle->aktifkanDariCuti($this->student);

        $this->assertEquals('Aktif', $result->status);
        $this->assertNull($result->cuti_from);
        $this->assertNull($result->cuti_until);
    }

    public function test_akhiri_cuti_pada_hari_cuti_until_diizinkan(): void
    {
        $this->student->update([
            'status'     => 'Cuti',
            'cuti_from'  => now()->subDays(30)->toDateString(),
            'cuti_until' => now()->toDateString(), // hari ini = hari terakhir cuti
        ]);

        $result = $this->lifecycle->aktifkanDariCuti($this->student);

        $this->assertEquals('Aktif', $result->status);
    }
}
