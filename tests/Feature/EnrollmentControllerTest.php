<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private Student $student;
    private Package $package;
    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->owner   = User::factory()->create()->assignRole('Owner');
        $this->admin   = User::factory()->create()->assignRole('Admin');
        $this->student = Student::factory()->create(['status' => 'Aktif']);
        $this->package = Package::factory()->create(['class_type' => 'REGULER']);
        $this->teacher = Teacher::factory()->create();
    }

    // ===== STORE =====

    public function test_admin_dapat_tambah_kelas_baru(): void
    {
        $room = Room::factory()->create();

        // Buat enrollment utama dulu
        $e1 = Enrollment::factory()->for($this->student)->create([
            'is_primary' => true, 'status' => 'ACTIVE',
        ]);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $response = $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $this->student),
            [
                'package_id'     => $this->package->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $room->id,
                'day_of_week'    => 1,
                'start_time'     => '16:00',
                'effective_date' => now()->addDay()->format('Y-m-d'),
                'jadikan_utama'  => false,
            ]
        );

        $response->assertRedirect();
        $this->assertEquals(2, $this->student->enrollments()->active()->count());
        // Enrollment lama tetap utama
        $this->student->refresh();
        $this->assertEquals($e1->id, $this->student->primary_enrollment_id);
    }

    public function test_tambah_kelas_dengan_tanggal_efektif_masa_lalu(): void
    {
        $room         = Room::factory()->create();
        $pastDate     = now()->subMonth()->toDateString();
        $primary      = Enrollment::factory()->for($this->student)->create([
            'is_primary' => true, 'status' => 'ACTIVE',
        ]);
        $this->student->update(['primary_enrollment_id' => $primary->id]);

        $response = $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $this->student),
            [
                'package_id'     => $this->package->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $room->id,
                'day_of_week'    => 1,
                'start_time'     => '16:00',
                'effective_date' => $pastDate,
                'jadikan_utama'  => false,
            ]
        );

        $response->assertRedirect();

        $newEnrollment = Enrollment::query()
            ->where('student_id', $this->student->id)
            ->where('package_id', $this->package->id)
            ->where('id', '!=', $primary->id)
            ->first();

        $this->assertNotNull($newEnrollment);
        $this->assertSame($pastDate, $newEnrollment->effective_date->toDateString());
        $this->assertSame('ACTIVE', $newEnrollment->status);
    }

    // ===== LIFECYCLE GATE — hanya murid Aktif boleh tambah kelas =====

    #[\PHPUnit\Framework\Attributes\DataProvider('statusYangDiblokProvider')]
    public function test_murid_non_aktif_tidak_bisa_tambah_kelas(string $status): void
    {
        $room    = Room::factory()->create();
        $student = Student::factory()->create(['status' => $status]);

        $response = $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $student),
            [
                'package_id'     => $this->package->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $room->id,
                'day_of_week'    => 1,
                'start_time'     => '16:00',
                'effective_date' => now()->addDay()->format('Y-m-d'),
            ]
        );

        $response->assertStatus(422);
        $this->assertEquals(0, $student->enrollments()->count());
    }

    public static function statusYangDiblokProvider(): array
    {
        return [
            'Mengundurkan Diri' => ['Mengundurkan Diri'],
            'Calon'             => ['Calon'],
            'Trial'             => ['Trial'],
            'Cuti'              => ['Cuti'],
            'Selesai'           => ['Selesai'],
        ];
    }

    public function test_tambah_kelas_dengan_jadikan_utama(): void
    {
        $room = Room::factory()->create();
        $e1 = Enrollment::factory()->for($this->student)->create([
            'is_primary' => true, 'status' => 'ACTIVE',
        ]);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $this->student),
            [
                'package_id'     => $this->package->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $room->id,
                'day_of_week'    => 3,
                'start_time'     => '14:00',
                'effective_date' => now()->addDay()->format('Y-m-d'),
                'jadikan_utama'  => true,
            ]
        );

        $this->student->refresh();
        $e1->refresh();
        $this->assertFalse((bool) $e1->is_primary);
        $this->assertNotEquals($e1->id, $this->student->primary_enrollment_id);
    }

    // ===== CONFLICT VALIDATION =====

    public function test_tambah_kelas_gagal_jika_guru_sudah_punya_jadwal_di_jam_sama(): void
    {
        $room  = Room::factory()->create(['capacity' => 1]);
        $room2 = Room::factory()->create(['capacity' => 1]);

        // Guru sudah punya jadwal hari Senin 15:00 untuk murid lain
        $otherStudent    = Student::factory()->create(['status' => 'Aktif']);
        $otherEnrollment = Enrollment::factory()->for($otherStudent)->create([
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $otherEnrollment->id,
            'day_of_week'   => 1,
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room->id,
            'is_active'     => true,
        ]);

        // Buat enrollment utama agar $this->student lolos lifecycle gate
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $response = $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $this->student),
            [
                'package_id'     => $this->package->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $room2->id, // ruang beda — tetap konflik karena gurunya sama
                'day_of_week'    => 1,
                'start_time'     => '15:00',
                'effective_date' => now()->addDay()->format('Y-m-d'),
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors(['teacher_id']);
        // Hanya e1 yang ada, enrollment baru tidak terbuat
        $this->assertEquals(1, $this->student->enrollments()->active()->count());
    }

    public function test_tambah_kelas_gagal_jika_ruangan_sudah_penuh(): void
    {
        $room = Room::factory()->create(['capacity' => 1]);

        // Ruangan sudah terisi murid lain di hari Rabu 14:00
        $otherTeacher    = Teacher::factory()->create();
        $otherStudent    = Student::factory()->create(['status' => 'Aktif']);
        $otherEnrollment = Enrollment::factory()->for($otherStudent)->create([
            'teacher_id' => $otherTeacher->id,
            'status'     => 'ACTIVE',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $otherEnrollment->id,
            'day_of_week'   => 3, // Rabu
            'start_time'    => '14:00',
            'end_time'      => '14:30',
            'room_id'       => $room->id,
            'is_active'     => true,
        ]);

        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $response = $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $this->student),
            [
                'package_id'     => $this->package->id,
                'teacher_id'     => $this->teacher->id, // guru beda — tetap konflik karena ruangnya penuh
                'room_id'        => $room->id,
                'day_of_week'    => 3,
                'start_time'     => '14:00',
                'effective_date' => now()->addDay()->format('Y-m-d'),
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors(['room_id']);
        $this->assertEquals(1, $this->student->enrollments()->active()->count());
    }

    // ===== SET PRIMARY =====

    public function test_admin_dapat_set_enrollment_sebagai_utama(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)
            ->patch(route('students.enrollments.set-primary', [$this->student, $e2]));

        $this->student->refresh();
        $e1->refresh();
        $e2->refresh();
        $this->assertEquals($e2->id, $this->student->primary_enrollment_id);
        $this->assertFalse((bool) $e1->is_primary);
        $this->assertTrue((bool) $e2->is_primary);
    }

    // ===== DESTROY =====

    public function test_hentikan_kelas_non_utama(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)
            ->delete(route('students.enrollments.destroy', [$this->student, $e2]));

        $e2->refresh();
        $this->assertEquals('INACTIVE', $e2->status);
        $this->student->refresh();
        $this->assertEquals($e1->id, $this->student->primary_enrollment_id);
    }

    public function test_hentikan_kelas_menghapus_sesi_future_orphan(): void
    {
        $enrollment = Enrollment::factory()->for($this->student)->create([
            'is_primary' => true,
            'status'     => 'ACTIVE',
            'teacher_id' => $this->teacher->id,
        ]);
        $this->student->update(['primary_enrollment_id' => $enrollment->id]);

        $schedule = Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'is_active'     => true,
        ]);

        $today = now()->toDateString();
        $futureDate = now()->addDays(7)->toDateString();

        $futureSession = ClassSession::factory()->create([
            'schedule_id'   => $schedule->id,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $this->student->id,
            'teacher_id'    => $this->teacher->id,
            'session_date'  => $futureDate,
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);
        $todaySession = ClassSession::factory()->create([
            'schedule_id'   => $schedule->id,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $this->student->id,
            'teacher_id'    => $this->teacher->id,
            'session_date'  => $today,
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('students.enrollments.destroy', [$this->student, $enrollment]));

        $this->assertDatabaseMissing('class_sessions', ['id' => $futureSession->id]);
        $this->assertDatabaseHas('class_sessions', ['id' => $todaySession->id]);
    }

    public function test_hentikan_kelas_utama_minta_konfirmasi_jika_ada_kelas_lain(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $response = $this->actingAs($this->admin)
            ->delete(route('students.enrollments.destroy', [$this->student, $e1]));

        $response->assertRedirect();
        $response->assertSessionHas('confirm_primary_swap');
        $e1->refresh();
        $this->assertEquals('ACTIVE', $e1->status);
    }

    public function test_hentikan_kelas_utama_dengan_konfirmasi_swap(): void
    {
        $e1 = Enrollment::factory()->for($this->student)->create(['is_primary' => true,  'status' => 'ACTIVE']);
        $e2 = Enrollment::factory()->for($this->student)->create(['is_primary' => false, 'status' => 'ACTIVE']);
        $this->student->update(['primary_enrollment_id' => $e1->id]);

        $this->actingAs($this->admin)->delete(
            route('students.enrollments.destroy', [$this->student, $e1]),
            ['new_primary_enrollment_id' => $e2->id]
        );

        $e1->refresh();
        $e2->refresh();
        $this->student->refresh();
        $this->assertEquals('INACTIVE', $e1->status);
        $this->assertEquals($e2->id, $this->student->primary_enrollment_id);
        // Pastikan enrollment pengganti benar-benar ditandai sebagai utama
        $this->assertTrue((bool) $e2->is_primary);
    }
}
