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

class TrialRescheduleTest extends TestCase
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

    /** Helper: buat murid Trial dengan enrollment + sesi trial */
    private function createTrialStudent(
        ?ClassSession $sessionOverride = null,
        string $sessionStatus = ClassSession::STATUS_SCHEDULED,
    ): array {
        $student = Student::factory()->create(['status' => 'Trial', 'trial_date' => now()->addDay()]);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create(['class_type' => 'REGULER', 'price_per_month' => 340000]);

        $enrollment = Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $package->id,
            'teacher_id'     => $teacher->id,
            'effective_date' => now()->toDateString(),
            'status'         => Enrollment::STATUS_TRIAL,
            'is_primary'     => false,
        ]);

        $trialAt = now()->addDay()->setTime(10, 0, 0);
        $session = $sessionOverride ?? ClassSession::create([
            'schedule_id'   => null,
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => $trialAt->toDateString(),
            'start_time'    => $trialAt->format('H:i:s'),
            'end_time'      => $trialAt->copy()->addMinutes(30)->format('H:i:s'),
            'status'        => $sessionStatus,
        ]);

        return compact('student', 'teacher', 'package', 'enrollment', 'session', 'trialAt');
    }

    public function test_reschedule_trial_hapus_sesi_lama_dan_buat_baru(): void
    {
        ['student' => $student, 'teacher' => $teacher, 'package' => $package, 'session' => $oldSession] =
            $this->createTrialStudent();

        $newTrialAt = now()->addDays(3)->setTime(14, 0, 0);

        $this->actingAs($this->admin)
            ->post(route('students.reschedule-trial', $student->id), [
                'trial_date'          => $newTrialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect(route('students.show', $student->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('class_sessions', ['id' => $oldSession->id]);
        $this->assertDatabaseHas('class_sessions', [
            'student_id'   => $student->id,
            'session_date' => $newTrialAt->toDateString(),
            'start_time'   => $newTrialAt->format('H:i:s'),
            'status'       => ClassSession::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseCount('class_sessions', 1);
    }

    public function test_reschedule_trial_dari_sesi_cancelled(): void
    {
        ['student' => $student, 'teacher' => $teacher, 'package' => $package, 'session' => $oldSession] =
            $this->createTrialStudent(sessionStatus: ClassSession::STATUS_CANCELLED);

        $newTrialAt = now()->addDays(2)->setTime(11, 0, 0);

        $this->actingAs($this->admin)
            ->post(route('students.reschedule-trial', $student->id), [
                'trial_date'          => $newTrialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('class_sessions', ['id' => $oldSession->id]);
        $this->assertDatabaseHas('class_sessions', [
            'student_id' => $student->id,
            'status'     => ClassSession::STATUS_SCHEDULED,
        ]);
    }

    public function test_reschedule_trial_buat_sesi_baru_jika_sesi_sudah_dihapus(): void
    {
        ['student' => $student, 'teacher' => $teacher, 'package' => $package, 'session' => $session] =
            $this->createTrialStudent();

        $session->delete();

        $newTrialAt = now()->addDays(2)->setTime(15, 30, 0);

        $this->actingAs($this->admin)
            ->post(route('students.reschedule-trial', $student->id), [
                'trial_date'          => $newTrialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('class_sessions', 1);
        $this->assertDatabaseHas('class_sessions', [
            'student_id'   => $student->id,
            'session_date' => $newTrialAt->toDateString(),
            'status'       => ClassSession::STATUS_SCHEDULED,
        ]);
    }

    public function test_reschedule_trial_ditolak_jika_sudah_hadir(): void
    {
        ['student' => $student, 'teacher' => $teacher, 'package' => $package] =
            $this->createTrialStudent(sessionStatus: ClassSession::STATUS_HADIR);

        $newTrialAt = now()->addDays(2)->setTime(10, 0, 0);

        $this->actingAs($this->admin)
            ->post(route('students.reschedule-trial', $student->id), [
                'trial_date'          => $newTrialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect(route('students.show', $student->id))
            ->assertSessionHas('error');
    }

    public function test_reschedule_trial_ditolak_untuk_murid_bukan_trial(): void
    {
        $student = Student::factory()->create(['status' => 'Calon']);
        $teacher = Teacher::factory()->create();
        $package = Package::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('students.reschedule-trial', $student->id), [
                'trial_date'          => now()->addDay()->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_reschedule_trial_cek_konflik_guru(): void
    {
        ['student' => $student, 'teacher' => $teacher, 'package' => $package] =
            $this->createTrialStudent();

        $otherStudent = Student::factory()->create(['status' => 'Aktif']);
        $newTrialAt   = now()->addDays(4)->setTime(10, 0, 0);

        ClassSession::factory()->create([
            'teacher_id'   => $teacher->id,
            'student_id'   => $otherStudent->id,
            'session_date' => $newTrialAt->toDateString(),
            'start_time'   => '10:00:00',
            'end_time'     => '10:30:00',
            'status'       => ClassSession::STATUS_SCHEDULED,
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.reschedule-trial', $student->id), [
                'trial_date'          => $newTrialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $teacher->id,
                'package_id'          => $package->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_reschedule_trial_update_enrollment_dan_trial_date(): void
    {
        ['student' => $student, 'enrollment' => $enrollment] = $this->createTrialStudent();

        $newTeacher = Teacher::factory()->create();
        $newPackage = Package::factory()->create(['class_type' => 'HOBBY', 'price_per_month' => 390000]);
        $newRoom    = Room::factory()->create();
        $newTrialAt = now()->addDays(5)->setTime(16, 0, 0);

        $this->actingAs($this->admin)
            ->post(route('students.reschedule-trial', $student->id), [
                'trial_date'          => $newTrialAt->format('Y-m-d\TH:i'),
                'assigned_teacher_id' => $newTeacher->id,
                'package_id'          => $newPackage->id,
                'assigned_room_id'    => $newRoom->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $student->refresh();
        $enrollment->refresh();

        $this->assertEquals($newPackage->id, $enrollment->package_id);
        $this->assertEquals($newTeacher->id, $enrollment->teacher_id);
        $this->assertEquals(
            $newTrialAt->format('Y-m-d H:i'),
            $student->trial_date->format('Y-m-d H:i')
        );

        $this->assertDatabaseHas('class_sessions', [
            'enrollment_id' => $enrollment->id,
            'teacher_id'    => $newTeacher->id,
            'room_id'       => $newRoom->id,
        ]);
    }
}
