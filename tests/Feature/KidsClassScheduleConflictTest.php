<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Konflik jadwal Kids Class (Opsi 2):
 * - KIDS_CLASS / KIDS_CLASS_BUNDLE boleh overlap dengan Kids lain di slot yang sama
 * - Tetap ditolak jika slot sudah dipakai kelas privat (REGULER/HOBBY)
 * - REGULER tetap ditolak jika slot sudah ada Kids
 */
class KidsClassScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Teacher $teacher;
    private Package $kidsBundlePackage;
    private Package $regularPackage;
    private Room $kidsRoom;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->admin  = User::factory()->create()->assignRole('Admin');
        $this->teacher = Teacher::factory()->create(['is_active' => true]);

        $kidsInstrument = Instrument::factory()->create([
            'name' => 'Kids Class',
            'code' => 'KIDS',
        ]);
        $pianoInstrument = Instrument::factory()->create([
            'name' => 'Piano',
            'code' => 'PIANO',
        ]);

        $this->kidsBundlePackage = Package::factory()->create([
            'class_type'      => 'KIDS_CLASS_BUNDLE',
            'instrument_id'   => $kidsInstrument->id,
            'duration_min'    => 45,
            'price_per_month' => 2180000,
        ]);
        $this->regularPackage = Package::factory()->create([
            'class_type'      => 'REGULER',
            'instrument_id'   => $pianoInstrument->id,
            'duration_min'    => 30,
            'price_per_month' => 370000,
        ]);

        $this->kidsRoom = Room::factory()->create([
            'code'                  => 'R1',
            'name'                  => 'Studio 1',
            'capacity'              => 4,
            'supported_instruments' => ['Kids Class', 'Vocal', 'Gitar'],
        ]);
    }

    /** Buat murid Aktif dengan enrollment utama agar lolos lifecycle gate. */
    private function makeActiveStudentWithPrimary(): Student
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $primary = Enrollment::factory()->for($student)->create([
            'package_id' => $this->regularPackage->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'is_primary' => true,
            'status'     => 'ACTIVE',
        ]);
        $student->update(['primary_enrollment_id' => $primary->id]);

        return $student;
    }

    /** Seed satu jadwal Kids di slot Senin 11:00. */
    private function seedExistingKidsSchedule(): Enrollment
    {
        $otherStudent = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->for($otherStudent)->create([
            'package_id' => $this->kidsBundlePackage->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);

        Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => 1,
            'start_time'    => '11:00',
            'end_time'      => '11:45',
            'room_id'       => $this->kidsRoom->id,
            'is_active'     => true,
        ]);

        return $enrollment;
    }

    public function test_tambah_kelas_kids_bundle_lolos_jika_guru_sudah_punya_jadwal_kids_lain(): void
    {
        $this->seedExistingKidsSchedule();
        $student = $this->makeActiveStudentWithPrimary();

        $response = $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $student),
            [
                'package_id'     => $this->kidsBundlePackage->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $this->kidsRoom->id,
                'day_of_week'    => 1,
                'start_time'     => '11:00',
                'effective_date' => now()->addDay()->format('Y-m-d'),
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'package_id' => $this->kidsBundlePackage->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);
    }

    public function test_tambah_kelas_kids_bundle_gagal_jika_slot_sudah_ada_reguler(): void
    {
        $otherStudent = Student::factory()->create(['status' => 'Aktif']);
        $otherEnrollment = Enrollment::factory()->for($otherStudent)->create([
            'package_id' => $this->regularPackage->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $otherEnrollment->id,
            'day_of_week'   => 1,
            'start_time'    => '11:00',
            'end_time'      => '11:30',
            'room_id'       => $this->kidsRoom->id,
            'is_active'     => true,
        ]);

        $student = $this->makeActiveStudentWithPrimary();

        $response = $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $student),
            [
                'package_id'     => $this->kidsBundlePackage->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $this->kidsRoom->id,
                'day_of_week'    => 1,
                'start_time'     => '11:00',
                'effective_date' => now()->addDay()->format('Y-m-d'),
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors(['teacher_id']);
    }

    public function test_tambah_kelas_reguler_gagal_jika_slot_sudah_ada_kids(): void
    {
        $this->seedExistingKidsSchedule();
        $student = $this->makeActiveStudentWithPrimary();

        $response = $this->actingAs($this->admin)->post(
            route('students.enrollments.store', $student),
            [
                'package_id'     => $this->regularPackage->id,
                'teacher_id'     => $this->teacher->id,
                'room_id'        => $this->kidsRoom->id,
                'day_of_week'    => 1,
                'start_time'     => '11:00',
                'effective_date' => now()->addDay()->format('Y-m-d'),
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors(['teacher_id']);
    }

    public function test_store_jadwal_kids_bundle_lolos_jika_guru_sudah_punya_jadwal_kids_lain(): void
    {
        $this->seedExistingKidsSchedule();

        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $this->kidsBundlePackage->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $response = $this->actingAs($this->admin)->post(
            route('schedules.store', $student->id),
            [
                'enrollment_id' => $enrollment->id,
                'day_of_week'   => 1,
                'start_time'    => '11:00',
                'end_time'      => '11:45',
                'room_id'       => $this->kidsRoom->id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('schedules', [
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => 1,
            'room_id'       => $this->kidsRoom->id,
        ]);
    }

    public function test_store_jadwal_kids_bundle_gagal_jika_slot_sudah_ada_reguler(): void
    {
        $otherStudent = Student::factory()->create(['status' => 'Aktif']);
        $otherEnrollment = Enrollment::factory()->for($otherStudent)->create([
            'package_id' => $this->regularPackage->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
        ]);
        Schedule::factory()->create([
            'enrollment_id' => $otherEnrollment->id,
            'day_of_week'   => 1,
            'start_time'    => '11:00',
            'end_time'      => '11:30',
            'room_id'       => $this->kidsRoom->id,
            'is_active'     => true,
        ]);

        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $this->kidsBundlePackage->id,
            'teacher_id' => $this->teacher->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        $response = $this->actingAs($this->admin)->post(
            route('schedules.store', $student->id),
            [
                'enrollment_id' => $enrollment->id,
                'day_of_week'   => 1,
                'start_time'    => '11:00',
                'end_time'      => '11:45',
                'room_id'       => $this->kidsRoom->id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
