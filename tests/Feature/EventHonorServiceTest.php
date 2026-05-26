<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Holiday;
use App\Models\HonorSlip;
use App\Models\PayrollConfig;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EventHonorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventHonorServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventHonorService $service;
    private int $createdBy;

    protected function setUp(): void
    {
        parent::setUp();
        // Buat user nyata agar FK created_by tidak violation saat service insert slip
        $user = \App\Models\User::factory()->create();
        $this->createdBy = $user->id;
        $this->service = app(EventHonorService::class);
    }

    private function makeEvent(string $date = '2026-06-15'): Event
    {
        return Event::factory()->create([
            'event_date' => $date,
            'status'     => Event::STATUS_COMPLETED,
            'name'       => 'Konser KITA Juni 2026',
        ]);
    }

    private function makeTeacher(): Teacher
    {
        return Teacher::factory()->create(['is_active' => true]);
    }

    private function addParticipantWithTeacher(Event $event, Teacher $accompanyingTeacher): EventParticipant
    {
        $student = Student::factory()->create();
        return EventParticipant::factory()->create([
            'event_id'                => $event->id,
            'student_id'              => $student->id,
            'accompanying_teacher_id' => $accompanyingTeacher->id,
        ]);
    }

    public function test_inject_honor_into_existing_slip(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        $slip = HonorSlip::factory()->create([
            'teacher_id'   => $teacher->id,
            'month'        => 6,
            'year'         => 2026,
            'base_honor'   => 100000,
            'event_honor'  => 0,
            'total_honor'  => 100000,  // eksplisit: total = base saja, sebelum event honor ditambah
            'status'       => HonorSlip::STATUS_CALCULATED,
        ]);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $slip->refresh();
        $this->assertEquals(1, $result['slips_updated']);
        $this->assertEquals(0, $result['slips_skipped']);
        $this->assertEquals(250000, $slip->event_honor);
        $this->assertEquals(350000, $slip->total_honor);
    }

    public function test_create_new_slip_when_none_exists(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertEquals(1, $result['slips_updated']);

        $slip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', 6)->where('year', 2026)
            ->first();

        $this->assertNotNull($slip);
        $this->assertEquals(250000, $slip->event_honor);
        $this->assertStringStartsWith('SLIP/2026/06/', $slip->slip_number);
        $this->assertEquals(HonorSlip::STATUS_DRAFT, $slip->status);
    }

    public function test_skip_locked_slip(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        HonorSlip::factory()->create([
            'teacher_id'  => $teacher->id,
            'month'       => 6,
            'year'        => 2026,
            'event_honor' => 0,
            'status'      => HonorSlip::STATUS_PAID,
        ]);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertEquals(0, $result['slips_updated']);
        $this->assertEquals(1, $result['slips_skipped']);
    }

    public function test_warning_when_no_internal_holiday(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertTrue($result['holiday_warning']);
    }

    public function test_no_warning_when_internal_holiday_exists(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        Holiday::factory()->create([
            'date'          => '2026-06-15',
            'type'          => 'Internal',
            'is_honor_paid' => false,
        ]);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertFalse($result['holiday_warning']);
    }

    public function test_multiple_teachers_get_separate_honor(): void
    {
        $event    = $this->makeEvent('2026-06-15');
        $teacher1 = $this->makeTeacher();
        $teacher2 = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher1);
        $this->addParticipantWithTeacher($event, $teacher2);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertEquals(2, $result['slips_updated']);

        foreach ([$teacher1, $teacher2] as $teacher) {
            $slip = HonorSlip::where('teacher_id', $teacher->id)
                ->where('month', 6)->where('year', 2026)->first();
            $this->assertNotNull($slip);
            $this->assertEquals(250000, $slip->event_honor);
        }
    }

    public function test_teacher_accompanying_multiple_students_gets_honor_once(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();

        $this->addParticipantWithTeacher($event, $teacher);
        $this->addParticipantWithTeacher($event, $teacher);
        $this->addParticipantWithTeacher($event, $teacher);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertEquals(1, $result['slips_updated']);

        $slip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', 6)->where('year', 2026)->first();
        $this->assertEquals(250000, $slip->event_honor);
    }

    public function test_honor_amount_read_from_payroll_config(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        PayrollConfig::factory()->create([
            'scenario_code'    => 'H_PENDAMPING',
            'formula_type'     => 'FIXED',
            'value_or_formula' => '300000',
            'is_active'        => true,
        ]);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $slip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', 6)->where('year', 2026)->first();
        $this->assertEquals(300000, $slip->event_honor);
    }

    public function test_note_appended_when_existing_event_honor_note(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $teacher = $this->makeTeacher();
        $this->addParticipantWithTeacher($event, $teacher);

        HonorSlip::factory()->create([
            'teacher_id'       => $teacher->id,
            'month'            => 6,
            'year'             => 2026,
            'event_honor'      => 200000,
            'event_honor_note' => 'Pendamping Ujian Mei',
            'status'           => HonorSlip::STATUS_CALCULATED,
        ]);

        $this->service->processEventCompletion($event, $this->createdBy);

        $slip = HonorSlip::where('teacher_id', $teacher->id)
            ->where('month', 6)->where('year', 2026)->first();

        $this->assertStringContainsString('Pendamping Ujian Mei', $slip->event_honor_note);
        $this->assertStringContainsString('Konser KITA Juni 2026', $slip->event_honor_note);
        $this->assertStringContainsString(' | ', $slip->event_honor_note);
        $this->assertEquals(450000, $slip->event_honor);
    }

    public function test_no_accompanying_teachers_returns_zero_updated(): void
    {
        $event   = $this->makeEvent('2026-06-15');
        $student = Student::factory()->create();

        EventParticipant::factory()->create([
            'event_id'                => $event->id,
            'student_id'              => $student->id,
            'accompanying_teacher_id' => null,
        ]);

        $result = $this->service->processEventCompletion($event, $this->createdBy);

        $this->assertEquals(0, $result['slips_updated']);
        $this->assertEquals(0, $result['slips_skipped']);
    }
}
