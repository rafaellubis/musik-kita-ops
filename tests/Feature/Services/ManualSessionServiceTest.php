<?php

namespace Tests\Feature\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ManualSessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ManualSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ManualSessionService $service;
    private Enrollment $enrollment;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ManualSessionService::class);

        $teacher = Teacher::factory()->create(['is_active' => true]);
        $package = Package::factory()->create([
            'class_type'      => 'REGULER',
            'duration_min'    => 30,
            'price_per_month' => 340000,
            'is_active'       => true,
        ]);
        $student = Student::factory()->create(['status' => 'Aktif']);
        $this->enrollment = Enrollment::factory()->create([
            'student_id'     => $student->id,
            'package_id'     => $package->id,
            'teacher_id'     => $teacher->id,
            'status'         => 'ACTIVE',
            'effective_date' => '2026-01-15',
        ]);
        $student->update(['primary_enrollment_id' => $this->enrollment->id]);
        $this->room = Room::factory()->create(['is_active' => true]);
    }

    public function test_create_manual_session_with_attribution(): void
    {
        $session = $this->service->create(
            enrollment: $this->enrollment,
            sessionDate: '2026-02-07',
            startTime: '14:00',
            roomId: $this->room->id,
            attributionYear: 2026,
            attributionMonth: 1,
            sessionSequence: 3,
        );

        $this->assertSame(ClassSession::TYPE_MANUAL, $session->session_type);
        $this->assertSame(2026, $session->attribution_year);
        $this->assertSame(1, $session->attribution_month);
        $this->assertSame(3, $session->session_sequence);
        $this->assertSame('2026-02-07', $session->session_date);
        $this->assertNull($session->schedule_id);
        $this->assertSame(ClassSession::STATUS_SCHEDULED, $session->status);
    }

    public function test_suggest_next_sequence_skips_filled_slots(): void
    {
        ClassSession::factory()->create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->enrollment->teacher_id,
            'session_date'       => '2026-01-16',
            'attribution_year'   => 2026,
            'attribution_month'  => 1,
            'session_sequence'   => 1,
            'session_type'       => ClassSession::TYPE_REGULAR,
        ]);
        ClassSession::factory()->create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->enrollment->teacher_id,
            'session_date'       => '2026-01-23',
            'attribution_year'   => 2026,
            'attribution_month'  => 1,
            'session_sequence'   => 2,
            'session_type'       => ClassSession::TYPE_REGULAR,
        ]);

        $this->assertSame(3, $this->service->suggestNextSequence($this->enrollment, 2026, 1));
    }

    public function test_reject_duplicate_sequence_in_same_attribution_period(): void
    {
        ClassSession::factory()->create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->enrollment->teacher_id,
            'session_date'       => '2026-01-16',
            'attribution_year'   => 2026,
            'attribution_month'  => 1,
            'session_sequence'   => 3,
            'session_type'       => ClassSession::TYPE_REGULAR,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sequence 3 sudah terpakai');

        $this->service->create(
            enrollment: $this->enrollment,
            sessionDate: '2026-02-07',
            startTime: '14:00',
            roomId: $this->room->id,
            attributionYear: 2026,
            attributionMonth: 1,
            sessionSequence: 3,
        );
    }

    public function test_slot_summary_returns_four_slots(): void
    {
        ClassSession::factory()->create([
            'enrollment_id'     => $this->enrollment->id,
            'student_id'        => $this->enrollment->student_id,
            'teacher_id'        => $this->enrollment->teacher_id,
            'session_date'      => '2026-01-16',
            'attribution_year'  => 2026,
            'attribution_month' => 1,
            'session_sequence'  => 1,
            'session_type'      => ClassSession::TYPE_REGULAR,
        ]);

        $summary = $this->service->slotSummary($this->enrollment, 2026, 1);

        $this->assertCount(4, $summary);
        $this->assertNotNull($summary[1]);
        $this->assertNull($summary[2]);
        $this->assertNull($summary[3]);
        $this->assertNull($summary[4]);
    }
}
