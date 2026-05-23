<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /** mulaiTrial membuat Enrollment status=TRIAL untuk murid */
    public function test_mulaiTrial_membuat_enrollment_trial(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->addDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'package_id' => $package->id,
            'teacher_id' => $teacher->id,
            'status'     => Enrollment::STATUS_TRIAL,
            'is_primary' => false,
        ]);
    }

    /** ClassSession trial sekarang punya enrollment_id (bukan NULL) */
    public function test_mulaiTrial_class_session_punya_enrollment_id(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->addDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('status', Enrollment::STATUS_TRIAL)
            ->first();

        $this->assertNotNull($enrollment);

        $this->assertDatabaseHas('class_sessions', [
            'student_id'    => $student->id,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    /** Honor trial dihitung berdasarkan paket — HADIR = H_TRIAL = harga x 50% / 4 */
    public function test_honor_hadir_trial_terhitung_sesuai_paket(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->addDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        $session = ClassSession::where('student_id', $student->id)->first();
        $this->assertNotNull($session, 'ClassSession harus terbuat setelah trial dijadwalkan');

        // Input absensi HADIR (AbsensiController tidak cek apakah sesi sudah lewat)
        $this->actingAs($this->admin)
            ->patchJson(route('absensi.update', $session->id), [
                'status' => 'HADIR',
            ])
            ->assertOk();

        $session->refresh();
        $this->assertEquals('H_TRIAL', $session->honor_code);
        // Rp 340.000 × 50% / 4 = Rp 42.500
        $this->assertEquals(42500, $session->honor_amount);
    }

    /** Honor trial NO-SHOW = Rp 0 (TRIAL_NS) sesuai BR-1.4 */
    public function test_honor_no_show_trial_adalah_nol(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->addDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        $session = ClassSession::where('student_id', $student->id)->first();
        $this->assertNotNull($session, 'ClassSession harus terbuat setelah trial dijadwalkan');

        $this->actingAs($this->admin)
            ->patchJson(route('absensi.update', $session->id), [
                'status' => 'HANGUS',
            ])
            ->assertOk();

        $session->refresh();
        $this->assertEquals('TRIAL_NS', $session->honor_code);
        $this->assertEquals(0, $session->honor_amount);
    }

    /** konversiAktif menutup enrollment TRIAL (→ COMPLETED) dan buat ACTIVE baru */
    public function test_konversiAktif_menutup_enrollment_trial(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        // Jadwalkan trial dulu
        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->addDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'status'     => Enrollment::STATUS_TRIAL,
        ]);

        // Konversi ke aktif
        $this->actingAs($this->admin)
            ->post(route('students.convert-active', $student->id), [
                'package_id'          => $package->id,
                'assigned_teacher_id' => $teacher->id,
            ])
            ->assertRedirect();

        // Enrollment TRIAL harus COMPLETED
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'package_id' => $package->id,
            'status'     => Enrollment::STATUS_COMPLETED,
        ]);

        // Enrollment ACTIVE baru harus ada
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'status'     => Enrollment::STATUS_ACTIVE,
            'is_primary' => true,
        ]);
    }

    /** mundurkan menutup enrollment TRIAL (→ COMPLETED) */
    public function test_mundur_dari_trial_menutup_enrollment_trial(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'price_per_month' => 340000,
        ]);

        // Jadwalkan trial dulu
        $this->actingAs($this->admin)
            ->post(route('students.start-trial', $student->id), [
                'trial_date'          => now()->addDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect();

        // Mundurkan dari status Trial
        $this->actingAs($this->admin)
            ->post(route('students.withdraw', $student->id), [
                'reason' => 'Tidak cocok dengan jadwal',
            ])
            ->assertRedirect();

        // Enrollment TRIAL harus COMPLETED
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'status'     => Enrollment::STATUS_COMPLETED,
        ]);

        // Student status harus Mengundurkan Diri
        $this->assertDatabaseHas('students', [
            'id'     => $student->id,
            'status' => 'Mengundurkan Diri',
        ]);
    }
}
