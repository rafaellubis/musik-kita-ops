<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruSessionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;
    private Teacher $teacher;
    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);

        $this->guruUser = User::factory()->create();
        $this->guruUser->assignRole('Guru');
        $this->teacher = Teacher::factory()->create(['user_id' => $this->guruUser->id]);

        $instr = Instrument::factory()->create(['name' => 'Piano', 'code' => 'PIANO']);
        $package = Package::factory()->create([
            'class_type' => 'REGULER',
            'instrument_id' => $instr->id,
        ]);
        $student = Student::factory()->create();
        $enroll = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'package_id' => $package->id,
            'status' => 'ACTIVE',
        ]);

        $this->session = ClassSession::factory()->create([
            'enrollment_id' => $enroll->id,
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => today()->toDateString(),
            'status' => 'HADIR',
        ]);
    }

    public function test_guest_cannot_access_history_endpoint(): void
    {
        $response = $this->get(route('guru.sesi.history', $this->session));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_guru_can_access_own_session_history(): void
    {
        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.sesi.history', $this->session));

        $response->assertStatus(200);
    }

    public function test_guru_cannot_access_other_teacher_session_history(): void
    {
        $otherTeacherUser = User::factory()->create();
        $otherTeacherUser->assignRole('Guru');
        $otherTeacher = Teacher::factory()->create(['user_id' => $otherTeacherUser->id]);

        $otherSession = ClassSession::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'session_date' => today()->toDateString(),
        ]);

        $response = $this->actingAs($this->guruUser)
            ->get(route('guru.sesi.history', $otherSession));

        $response->assertStatus(403);
    }
}
