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
        $piano = Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        $gitar = Instrument::create(['name' => 'Gitar', 'code' => 'GITAR', 'is_active' => true, 'sort_order' => 2]);

        $pkgPiano = Package::create([
            'code'            => 'REG-PIANO-L1',
            'instrument_id'   => $piano->id,
            'class_type'      => 'REGULER',
            'grade'           => 'Level 1',
            'duration_min'    => 30,
            'price_per_month' => 370000,
            'is_active'       => true,
            'sort_order'      => 1,
        ]);
        $pkgGitar = Package::create([
            'code'            => 'HOBBY-GITAR',
            'instrument_id'   => $gitar->id,
            'class_type'      => 'HOBBY',
            'grade'           => null,
            'duration_min'    => 30,
            'price_per_month' => 390000,
            'is_active'       => true,
            'sort_order'      => 2,
        ]);

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
}
