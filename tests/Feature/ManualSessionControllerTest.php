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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    }

    public function test_admin_can_create_manual_session(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $teacher = Teacher::factory()->create(['is_active' => true]);
        $package = Package::factory()->create(['class_type' => 'REGULER', 'duration_min' => 30, 'is_active' => true]);
        $student = Student::factory()->create(['status' => 'Aktif']);
        $enrollment = Enrollment::factory()->create([
            'student_id'     => $student->id,
            'package_id'     => $package->id,
            'teacher_id'     => $teacher->id,
            'status'         => 'ACTIVE',
            'effective_date' => '2026-01-15',
        ]);
        $room = Room::factory()->create(['is_active' => true]);

        ClassSession::factory()->create([
            'enrollment_id'     => $enrollment->id,
            'student_id'        => $student->id,
            'teacher_id'        => $teacher->id,
            'session_date'      => '2026-01-16',
            'attribution_year'  => 2026,
            'attribution_month' => 1,
            'session_sequence'  => 1,
            'session_type'      => ClassSession::TYPE_REGULAR,
        ]);
        ClassSession::factory()->create([
            'enrollment_id'     => $enrollment->id,
            'student_id'        => $student->id,
            'teacher_id'        => $teacher->id,
            'session_date'      => '2026-01-23',
            'attribution_year'  => 2026,
            'attribution_month' => 1,
            'session_sequence'  => 2,
            'session_type'      => ClassSession::TYPE_REGULAR,
        ]);

        $response = $this->actingAs($admin)->post(
            route('students.enrollments.manual-sessions.store', [$student, $enrollment]),
            [
                'session_date'      => '2026-02-07',
                'start_time'        => '14:00',
                'room_id'           => $room->id,
                'attribution_year'  => 2026,
                'attribution_month' => 1,
                'session_sequence'  => 3,
            ],
        );

        $response->assertRedirect(route('students.show', $student) . '#tab-kelas');
        $this->assertDatabaseHas('class_sessions', [
            'enrollment_id'     => $enrollment->id,
            'session_date'      => '2026-02-07',
            'attribution_year'  => 2026,
            'attribution_month' => 1,
            'session_sequence'  => 3,
            'session_type'      => ClassSession::TYPE_MANUAL,
        ]);
    }
}
