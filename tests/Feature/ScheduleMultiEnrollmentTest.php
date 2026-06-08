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
 * Test untuk perbaikan Schedule CRUD — multi-enrollment + bookedSchedules fix.
 * Spec: docs/superpowers/specs/2026-06-03-schedule-absensi-redesign.md
 */
class ScheduleMultiEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * Helper: buat murid dengan dua enrollment ACTIVE (Piano + Gitar).
     * Return [$student, $enrollPiano, $enrollGitar, $teacher]
     */
    private function makeStudentWithTwoEnrollments(): array
    {
        // firstOrCreate agar bisa dipanggil lebih dari sekali dalam satu test session
        $piano = Instrument::firstOrCreate(['code' => 'PIANO'], ['name' => 'Piano', 'is_active' => true, 'sort_order' => 1]);
        $gitar = Instrument::firstOrCreate(['code' => 'GITAR'], ['name' => 'Gitar', 'is_active' => true, 'sort_order' => 2]);

        $pkgPiano = Package::firstOrCreate(
            ['code' => 'REG-PIANO-L1'],
            ['instrument_id' => $piano->id, 'class_type' => 'REGULER', 'grade' => 'Level 1',
             'duration_min' => 30, 'price_per_month' => 370000, 'is_active' => true, 'sort_order' => 1]
        );
        $pkgGitar = Package::firstOrCreate(
            ['code' => 'HOBBY-GITAR'],
            ['instrument_id' => $gitar->id, 'class_type' => 'HOBBY', 'grade' => null,
             'duration_min' => 30, 'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 2]
        );

        $teacher = Teacher::factory()->create(['is_active' => true]);
        $student = Student::factory()->create(['status' => 'Aktif']);

        $enrollPiano = Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $pkgPiano->id,
            'teacher_id'     => $teacher->id,
            'status'         => 'ACTIVE',
            'is_primary'     => true,
            'effective_date' => now()->toDateString(),
        ]);
        $enrollGitar = Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $pkgGitar->id,
            'teacher_id'     => $teacher->id,
            'status'         => 'ACTIVE',
            'is_primary'     => false,
            'effective_date' => now()->toDateString(),
        ]);

        $student->update(['primary_enrollment_id' => $enrollPiano->id]);

        return [$student, $enrollPiano, $enrollGitar, $teacher];
    }

    // =================== TASK 1 (A3) ===================

    /**
     * A3: $bookedSchedules yang dikirim ke view harus menyertakan field 'id'
     * agar Alpine.js bisa exclude jadwal yang sedang diedit dari conflict check.
     */
    public function test_student_show_view_has_booked_schedules_with_id(): void
    {
        [$student, $enrollPiano] = $this->makeStudentWithTwoEnrollments();

        // Buat room dan jadwal yang punya room_id
        $room = Room::create([
            'code'                  => 'R2',
            'name'                  => 'Studio 2',
            'capacity'              => 1,
            'supported_instruments' => ['Piano'],
            'is_active'             => true,
        ]);
        $schedule = Schedule::create([
            'enrollment_id' => $enrollPiano->id,
            'day_of_week'   => 1,
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'room_id'       => $room->id,
            'is_active'     => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('students.show', $student->id));

        $response->assertOk();

        // bookedSchedules harus ada di view dan tiap item harus punya 'id'
        $bookedSchedules = $response->viewData('bookedSchedules');
        $this->assertNotEmpty($bookedSchedules);

        $first = $bookedSchedules->first();
        $this->assertNotNull($first->id, 'bookedSchedules harus menyertakan kolom id');
        $this->assertSame($schedule->id, $first->id);
    }

    // =================== TASK 2 (A1+A2) ===================

    /**
     * A1: store() harus pakai enrollment_id dari request, bukan latest().
     * Kirim enrollment_id Gitar → jadwal harus ke Gitar, bukan Piano (primary).
     * Ini membuktikan bahwa controller TIDAK memakai latest() blind.
     */
    public function test_store_attaches_schedule_to_requested_enrollment_not_latest(): void
    {
        [$student, $enrollPiano, $enrollGitar] = $this->makeStudentWithTwoEnrollments();

        // Kirim Gitar secara eksplisit — kalau controller pakai latest() saja
        // dan Piano adalah primary, ini akan salah attach.
        $this->actingAs($this->admin)
            ->post(route('schedules.store', $student->id), [
                'enrollment_id' => $enrollGitar->id,
                'day_of_week'   => 2,
                'start_time'    => '14:00',
                'end_time'      => '14:30',
            ])
            ->assertRedirect();

        // Jadwal harus attach ke Gitar (yang dikirim), bukan Piano
        $this->assertDatabaseHas('schedules', [
            'enrollment_id' => $enrollGitar->id,
            'day_of_week'   => 2,
        ]);
        $this->assertDatabaseMissing('schedules', [
            'enrollment_id' => $enrollPiano->id,
        ]);
    }

    /**
     * A1: store() harus tolak enrollment_id yang bukan milik student ini.
     */
    public function test_store_rejects_enrollment_belonging_to_another_student(): void
    {
        [$student] = $this->makeStudentWithTwoEnrollments();

        // Buat enrollment milik student lain
        $otherStudent = Student::factory()->create(['status' => 'Aktif']);
        $teacher2     = Teacher::factory()->create(['is_active' => true]);
        $otherEnroll  = Enrollment::create([
            'student_id'     => $otherStudent->id,
            'package_id'     => Package::first()->id,
            'teacher_id'     => $teacher2->id,
            'status'         => 'ACTIVE',
            'is_primary'     => true,
            'effective_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('schedules.store', $student->id), [
                'enrollment_id' => $otherEnroll->id,
                'day_of_week'   => 2,
                'start_time'    => '11:00',
                'end_time'      => '11:30',
            ]);

        $response->assertStatus(403);
    }

    /**
     * A1: store() tanpa enrollment_id harus gagal validasi (400/redirect with errors).
     */
    public function test_store_requires_enrollment_id(): void
    {
        [$student] = $this->makeStudentWithTwoEnrollments();

        $this->actingAs($this->admin)
            ->post(route('schedules.store', $student->id), [
                'day_of_week' => 1,
                'start_time'  => '10:00',
                'end_time'    => '10:30',
            ])
            ->assertSessionHasErrors('enrollment_id');
    }

    // =================== TASK 3 (A5) ===================

    /**
     * Helper: buat schedule langsung terkait ke enrollment murid tertentu.
     */
    private function makeScheduleForEnrollment(Enrollment $enrollment): Schedule
    {
        return Schedule::create([
            'enrollment_id' => $enrollment->id,
            'day_of_week'   => 1,
            'start_time'    => '09:00:00',
            'end_time'      => '09:30:00',
            'room_id'       => null,
            'is_active'     => true,
        ]);
    }

    /**
     * A5: update() dengan student yang salah (jadwal milik murid lain) harus 403.
     */
    public function test_update_with_wrong_student_returns_403(): void
    {
        [$studentA, $enrollA] = $this->makeStudentWithTwoEnrollments();
        $scheduleA = $this->makeScheduleForEnrollment($enrollA);

        // studentB berbeda, tidak punya hubungan ke scheduleA
        [$studentB] = $this->makeStudentWithTwoEnrollments();

        $this->actingAs($this->admin)
            ->patch(route('schedules.update', [$studentB->id, $scheduleA->id]), [
                'day_of_week' => 2,
                'start_time'  => '10:00',
                'end_time'    => '10:30',
            ])
            ->assertStatus(403);
    }

    /**
     * A5: destroy() dengan student yang salah harus 403.
     */
    public function test_destroy_with_wrong_student_returns_403(): void
    {
        [$studentA, $enrollA] = $this->makeStudentWithTwoEnrollments();
        $scheduleA = $this->makeScheduleForEnrollment($enrollA);

        [$studentB] = $this->makeStudentWithTwoEnrollments();

        $this->actingAs($this->admin)
            ->delete(route('schedules.destroy', [$studentB->id, $scheduleA->id]))
            ->assertStatus(403);
    }

    /**
     * A5: toggleActive() dengan student yang salah harus 403.
     */
    public function test_toggle_active_with_wrong_student_returns_403(): void
    {
        [$studentA, $enrollA] = $this->makeStudentWithTwoEnrollments();
        $scheduleA = $this->makeScheduleForEnrollment($enrollA);

        [$studentB] = $this->makeStudentWithTwoEnrollments();

        $this->actingAs($this->admin)
            ->post(route('schedules.toggle-active', [$studentB->id, $scheduleA->id]))
            ->assertStatus(403);
    }

    /**
     * A5: update() dengan student yang BENAR harus berhasil (redirect).
     */
    public function test_update_with_correct_student_succeeds(): void
    {
        [$student, $enrollPiano] = $this->makeStudentWithTwoEnrollments();
        $schedule = $this->makeScheduleForEnrollment($enrollPiano);

        $this->actingAs($this->admin)
            ->patch(route('schedules.update', [$student->id, $schedule->id]), [
                'day_of_week' => 3,
                'start_time'  => '11:00',
                'end_time'    => '11:30',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schedules', [
            'id'          => $schedule->id,
            'day_of_week' => 3,
        ]);
    }

    // =================== TASK 4 (S1) ===================

    /**
     * S1: store() harus menolak jadwal baru jika murid sudah punya jadwal aktif
     * di hari dan jam yang overlap (multi-enrollment double-booking).
     */
    public function test_store_menolak_jadwal_jika_murid_sudah_punya_jadwal_aktif_di_jam_yang_sama(): void
    {
        [$student, $enrollPiano, $enrollGitar, $teacher] = $this->makeStudentWithTwoEnrollments();

        $room1 = Room::factory()->create([
            'capacity'              => 1,
            'is_active'             => true,
            'supported_instruments' => ['Piano'],
        ]);
        $room2 = Room::factory()->create([
            'capacity'              => 1,
            'is_active'             => true,
            'supported_instruments' => ['Gitar'],
        ]);

        // Buat jadwal Piano di Senin 15:00–15:30
        Schedule::factory()->create([
            'enrollment_id' => $enrollPiano->id,
            'day_of_week'   => 1,
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room1->id,
            'is_active'     => true,
        ]);

        // Coba tambah jadwal Gitar di Senin 15:10–15:40 (overlap dengan Piano)
        $teacherGitar = Teacher::factory()->create(['is_active' => true]);
        $enrollGitar->update(['teacher_id' => $teacherGitar->id]);

        $response = $this->actingAs($this->admin)
            ->post(route('schedules.store', $student), [
                'enrollment_id' => $enrollGitar->id,
                'day_of_week'   => 1,
                'start_time'    => '15:10',
                'end_time'      => '15:40',
                'room_id'       => $room2->id,
            ]);

        // Controller menggunakan flash 'error', bukan validation errors
        $response->assertSessionHas('error');
    }

    /**
     * S1: store() harus mengizinkan jadwal baru jika di hari yang berbeda.
     */
    public function test_store_mengizinkan_jadwal_jika_murid_punya_jadwal_di_hari_berbeda(): void
    {
        [$student, $enrollPiano, $enrollGitar, $teacher] = $this->makeStudentWithTwoEnrollments();

        $room1 = Room::factory()->create([
            'capacity'              => 1,
            'is_active'             => true,
            'supported_instruments' => ['Piano'],
        ]);
        $room2 = Room::factory()->create([
            'capacity'              => 1,
            'is_active'             => true,
            'supported_instruments' => ['Gitar'],
        ]);

        Schedule::factory()->create([
            'enrollment_id' => $enrollPiano->id,
            'day_of_week'   => 1, // Senin
            'start_time'    => '15:00',
            'end_time'      => '15:30',
            'room_id'       => $room1->id,
            'is_active'     => true,
        ]);

        $teacherGitar = Teacher::factory()->create(['is_active' => true]);
        $enrollGitar->update(['teacher_id' => $teacherGitar->id]);

        // Rabu (hari berbeda) — harus diizinkan
        $response = $this->actingAs($this->admin)
            ->post(route('schedules.store', $student), [
                'enrollment_id' => $enrollGitar->id,
                'day_of_week'   => 3, // Rabu
                'start_time'    => '15:00',
                'end_time'      => '15:30',
                'room_id'       => $room2->id,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');
    }
}
