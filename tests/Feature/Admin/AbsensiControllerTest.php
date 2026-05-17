<?php

namespace Tests\Feature\Admin;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test untuk AbsensiController (M04 — Absensi Harian).
 * Task 1: scaffold dasar — route terdaftar, halaman bisa diakses.
 */
class AbsensiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat role yang dibutuhkan — pola sama dengan test lain di project ini
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * Helper: buat ClassSession lengkap dengan seluruh chain relasi.
     * Dipakai di semua test agar tidak ada duplikasi setup.
     */
    private function createTestSession(array $overrides = []): ClassSession
    {
        $teacher    = Teacher::factory()->create(['is_active' => true]);
        $student    = Student::factory()->create();
        $room       = Room::factory()->create(['is_active' => true]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule = Schedule::factory()->create([
            'enrollment_id' => $enrollment->id,
            'room_id'       => $room->id,
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
        ]);

        return ClassSession::factory()->create(array_merge([
            'schedule_id'   => $schedule->id,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'room_id'       => $room->id,
            'session_date'  => today(),
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ], $overrides));
    }

    public function test_halaman_absensi_dapat_diakses_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.absensi.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.absensi.index');
    }

    public function test_halaman_absensi_dapat_diakses_owner(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('admin.absensi.index'));

        $response->assertStatus(200);
    }

    public function test_halaman_absensi_tidak_bisa_diakses_guest(): void
    {
        $response = $this->get(route('admin.absensi.index'));

        // Guest diredirect ke halaman login
        $response->assertRedirect(route('login'));
    }
}
