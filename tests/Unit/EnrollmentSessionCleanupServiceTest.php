<?php

namespace Tests\Unit;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EnrollmentSessionCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentSessionCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentSessionCleanupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnrollmentSessionCleanupService::class);
    }

    public function test_menghapus_sesi_scheduled_dan_libur_setelah_end_date(): void
    {
        $student    = Student::factory()->create(['status' => 'Aktif']);
        $teacher    = Teacher::factory()->create();
        $enrollment = Enrollment::factory()->for($student)->create([
            'teacher_id' => $teacher->id,
            'status'     => 'INACTIVE',
            'end_date'   => '2026-06-02',
        ]);

        $futureScheduled = ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => '2026-06-04',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);
        $futureLibur = ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => '2026-06-11',
            'status'        => ClassSession::STATUS_LIBUR,
        ]);
        $onEndDate = ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => '2026-06-02',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);
        $hadir = ClassSession::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => '2026-06-18',
            'status'        => ClassSession::STATUS_HADIR,
        ]);

        $deleted = $this->service->purgeFutureSessions($enrollment);

        $this->assertSame(2, $deleted);
        $this->assertDatabaseMissing('class_sessions', ['id' => $futureScheduled->id]);
        $this->assertDatabaseMissing('class_sessions', ['id' => $futureLibur->id]);
        $this->assertDatabaseHas('class_sessions', ['id' => $onEndDate->id]);
        $this->assertDatabaseHas('class_sessions', ['id' => $hadir->id]);
    }

    public function test_tidak_menghapus_sesi_enrollment_lain(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $teacher = Teacher::factory()->create();

        $closed = Enrollment::factory()->for($student)->create([
            'teacher_id' => $teacher->id,
            'status'     => 'INACTIVE',
            'end_date'   => '2026-06-02',
        ]);
        $active = Enrollment::factory()->for($student)->create([
            'teacher_id' => $teacher->id,
            'status'     => 'ACTIVE',
            'end_date'   => null,
        ]);

        $orphan = ClassSession::factory()->create([
            'enrollment_id' => $closed->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => '2026-06-04',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);
        $keep = ClassSession::factory()->create([
            'enrollment_id' => $active->id,
            'student_id'    => $student->id,
            'teacher_id'    => $teacher->id,
            'session_date'  => '2026-06-04',
            'status'        => ClassSession::STATUS_SCHEDULED,
        ]);

        $this->service->purgeFutureSessions($closed);

        $this->assertDatabaseMissing('class_sessions', ['id' => $orphan->id]);
        $this->assertDatabaseHas('class_sessions', ['id' => $keep->id]);
    }
}
