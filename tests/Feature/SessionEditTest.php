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

class SessionEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ClassSession $session;
    private Teacher $teacher;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->teacher = Teacher::factory()->create();
        $this->room    = Room::factory()->create();

        $student    = Student::factory()->create(['status' => 'Aktif']);
        $package    = Package::factory()->create(['class_type' => 'REGULER', 'price_per_month' => 340000]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'package_id' => $package->id,
            'teacher_id' => $this->teacher->id,
            'status'     => Enrollment::STATUS_ACTIVE,
            'is_primary' => true,
        ]);

        $this->session = ClassSession::factory()->create([
            'student_id'    => $student->id,
            'teacher_id'    => $this->teacher->id,
            'enrollment_id' => $enrollment->id,
            'room_id'       => $this->room->id,
            'session_date'  => now()->toDateString(),
            'start_time'    => '10:00:00',
            'end_time'      => '10:30:00',
            'status'        => 'SCHEDULED',
        ]);
    }

    public function test_admin_bisa_edit_jam_sesi(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '11:00',
                'end_time'   => '11:30',
                'teacher_id' => $this->teacher->id,
                'room_id'    => $this->room->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'id'         => $this->session->id,
            'start_time' => '11:00:00',
            'end_time'   => '11:30:00',
        ]);
    }

    public function test_admin_bisa_ganti_guru(): void
    {
        $guru2 = Teacher::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '10:00',
                'end_time'   => '10:30',
                'teacher_id' => $guru2->id,
                'room_id'    => $this->room->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'id'         => $this->session->id,
            'teacher_id' => $guru2->id,
        ]);
    }

    public function test_admin_bisa_ganti_ruang(): void
    {
        $ruang2 = Room::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '10:00',
                'end_time'   => '10:30',
                'teacher_id' => $this->teacher->id,
                'room_id'    => $ruang2->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'id'      => $this->session->id,
            'room_id' => $ruang2->id,
        ]);
    }

    public function test_konflik_guru_ditolak(): void
    {
        $guru2    = Teacher::factory()->create();
        $student2 = Student::factory()->create(['status' => 'Aktif']);

        ClassSession::factory()->create([
            'teacher_id'   => $guru2->id,
            'student_id'   => $student2->id,
            'session_date' => $this->session->session_date,
            'start_time'   => '10:00:00',
            'end_time'     => '10:30:00',
            'status'       => 'SCHEDULED',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '10:00',
                'end_time'   => '10:30',
                'teacher_id' => $guru2->id,
                'room_id'    => $this->room->id,
            ])
            ->assertSessionHasErrors(['teacher_id']);

        $this->assertDatabaseHas('class_sessions', [
            'id'         => $this->session->id,
            'teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_konflik_ruang_ditolak(): void
    {
        $ruang2   = Room::factory()->create();
        $guru2    = Teacher::factory()->create();
        $student2 = Student::factory()->create(['status' => 'Aktif']);

        ClassSession::factory()->create([
            'teacher_id'   => $guru2->id,
            'student_id'   => $student2->id,
            'room_id'      => $ruang2->id,
            'session_date' => $this->session->session_date,
            'start_time'   => '10:00:00',
            'end_time'     => '10:30:00',
            'status'       => 'SCHEDULED',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '10:00',
                'end_time'   => '10:30',
                'teacher_id' => $this->teacher->id,
                'room_id'    => $ruang2->id,
            ])
            ->assertSessionHasErrors(['room_id']);
    }

    public function test_sesi_cancelled_tidak_conflict(): void
    {
        $guru2    = Teacher::factory()->create();
        $student2 = Student::factory()->create(['status' => 'Aktif']);

        ClassSession::factory()->create([
            'teacher_id'   => $guru2->id,
            'student_id'   => $student2->id,
            'session_date' => $this->session->session_date,
            'start_time'   => '10:00:00',
            'end_time'     => '10:30:00',
            'status'       => 'CANCELLED',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '10:00',
                'end_time'   => '10:30',
                'teacher_id' => $guru2->id,
                'room_id'    => $this->room->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_edit_sesi_status_hadir_diizinkan(): void
    {
        $this->session->update(['status' => 'HADIR']);

        $this->actingAs($this->admin)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '09:00',
                'end_time'   => '09:30',
                'teacher_id' => $this->teacher->id,
                'room_id'    => $this->room->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_sessions', [
            'id'         => $this->session->id,
            'start_time' => '09:00:00',
        ]);
    }

    public function test_auditor_tidak_boleh_edit(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('Auditor');

        $this->actingAs($auditor)
            ->patch(route('sessions.update', $this->session->id), [
                'start_time' => '11:00',
                'end_time'   => '11:30',
                'teacher_id' => $this->teacher->id,
                'room_id'    => $this->room->id,
            ])
            ->assertForbidden();
    }
}
