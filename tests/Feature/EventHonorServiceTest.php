<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Event;
use App\Models\EventHonorEntry;
use App\Models\Holiday;
use App\Models\HonorSlip;
use App\Models\PayrollConfig;
use App\Models\SessionTeacherNote;
use App\Models\Teacher;
use App\Models\User;
use App\Services\EventHonorService;
use App\Services\EventLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesEventHonorScenario;
use Tests\TestCase;

class EventHonorServiceTest extends TestCase
{
    use RefreshDatabase, CreatesEventHonorScenario;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 20));
        foreach (['Owner', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->actor = User::factory()->create()->assignRole('Owner');
    }

    public function test_post_updates_existing_slip_preserving_base_and_manual_components(): void
    {
        [$event, $participants] = $this->readyEvent($this->actor);
        $slip = HonorSlip::factory()->create([
            'teacher_id' => $participants->first()->activeTeacherAssignment->teacher_id,
            'year' => 2026, 'month' => 6, 'status' => 'CALCULATED',
            'base_honor' => 100000, 'transport_honor' => 10000, 'other_honor' => 5000, 'other_honor_note' => 'Bonus',
        ]);
        app(EventLifecycleService::class)->complete($event, $this->actor);
        $this->assertSame(42500, $slip->fresh()->event_honor);
        $this->assertSame(157500, $slip->fresh()->total_honor);
        $this->assertSame('Bonus', $slip->fresh()->other_honor_note);
        $this->assertSame('CALCULATED', $slip->fresh()->status);
        $this->assertSame($slip->id, EventHonorEntry::sole()->honor_slip_id);
    }

    public function test_post_creates_a_draft_slip_using_existing_monthly_number_format(): void
    {
        [$event] = $this->readyEvent($this->actor);
        app(EventLifecycleService::class)->complete($event, $this->actor);
        $slip = HonorSlip::sole();
        $this->assertSame('SLIP/2026/06/0001', $slip->slip_number);
        $this->assertSame('DRAFT', $slip->status);
        $this->assertSame(42500, $slip->total_honor);
    }

    public function test_paid_slip_rejects_entire_completion_instead_of_skipping_honor(): void
    {
        [$event, $participants] = $this->readyEvent($this->actor);
        $slip = HonorSlip::factory()->create([
            'teacher_id' => $participants->first()->activeTeacherAssignment->teacher_id,
            'year' => 2026, 'month' => 6, 'status' => 'PAID',
        ]);
        $this->actingAs($this->actor)->postJson(route('events.complete', $event))->assertUnprocessable();
        $this->assertSame('PUBLISHED', $event->fresh()->status);
        $this->assertSame(0, $slip->fresh()->event_honor);
        $this->assertDatabaseCount('event_honor_entries', 0);
    }

    public function test_holiday_presence_does_not_change_snapshot_honor_or_sessions(): void
    {
        [$event] = $this->readyEvent($this->actor);
        $holiday = Holiday::factory()->create(['date' => '2026-06-15', 'type' => 'Internal', 'is_honor_paid' => false]);
        $before = $holiday->fresh()->getAttributes();
        app(EventLifecycleService::class)->complete($event, $this->actor);
        $this->assertSame(42500, HonorSlip::sole()->event_honor);
        $this->assertSame($before, $holiday->fresh()->getAttributes());
        $this->assertDatabaseCount('class_sessions', 0);
    }

    public function test_completion_does_not_mutate_regular_sessions_or_teacher_notes(): void
    {
        [$event, $participants] = $this->readyEvent($this->actor);
        $teacher = $participants->first()->activeTeacherAssignment->teacher;
        $session = ClassSession::factory()->create([
            'teacher_id' => $teacher->id,
            'session_date' => '2026-06-15',
            'notes' => 'Catatan sesi tetap',
        ]);
        $note = SessionTeacherNote::create([
            'class_session_id' => $session->id,
            'teacher_id' => $teacher->id,
            'material_learned' => 'Materi tetap',
            'notes' => 'Catatan guru tetap',
        ]);
        $sessionBefore = $session->fresh()->getAttributes();
        $noteBefore = $note->fresh()->getAttributes();

        app(EventLifecycleService::class)->complete($event, $this->actor);

        $this->assertSame($sessionBefore, $session->fresh()->getAttributes());
        $this->assertSame($noteBefore, $note->fresh()->getAttributes());
    }

    public function test_each_teacher_receives_their_own_participant_entries(): void
    {
        [$first] = $this->readyEvent($this->actor);
        [$second] = $this->readyEvent($this->actor);
        app(EventLifecycleService::class)->complete($first, $this->actor);
        app(EventLifecycleService::class)->complete($second, $this->actor);
        $this->assertDatabaseCount('teacher_honor_slips', 2);
        $this->assertSame([42500, 42500], HonorSlip::orderBy('id')->pluck('event_honor')->all());
        $this->assertSame(['SLIP/2026/06/0001', 'SLIP/2026/06/0002'], HonorSlip::orderBy('id')->pluck('slip_number')->all());
    }

    public function test_teacher_with_multiple_participants_is_paid_per_participation(): void
    {
        [$event] = $this->readyEvent($this->actor, 3);
        app(EventLifecycleService::class)->complete($event, $this->actor);
        $this->assertDatabaseCount('event_honor_entries', 3);
        $this->assertSame(127500, HonorSlip::sole()->event_honor);
    }

    public function test_old_pendamping_config_cannot_override_participant_snapshot(): void
    {
        [$event] = $this->readyEvent($this->actor);
        PayrollConfig::factory()->create([
            'scenario_code' => 'H_PENDAMPING', 'formula_type' => 'FIXED', 'value_or_formula' => '300000', 'is_active' => true,
        ]);
        app(EventLifecycleService::class)->complete($event, $this->actor);
        $this->assertSame(42500, HonorSlip::sole()->event_honor);
    }

    public function test_recalculation_uses_only_posted_entries_for_exact_teacher_month_and_year(): void
    {
        $slip = HonorSlip::factory()->create(['year' => 2026, 'month' => 6]);
        foreach ([['2026-06-15', 'POSTED', $slip->teacher_id], ['2026-06-15', 'REVERSED', $slip->teacher_id],
            ['2026-07-15', 'POSTED', $slip->teacher_id], ['2025-06-15', 'POSTED', $slip->teacher_id],
            ['2026-06-15', 'POSTED', Teacher::factory()->create()->id]] as [$date, $status, $teacherId]) {
            $event = Event::factory()->completed()->create(['event_date' => $date]);
            EventHonorEntry::factory()->create([
                'event_id' => $event->id, 'teacher_id' => $teacherId, 'status' => $status, 'honor_slip_id' => $slip->id,
            ]);
        }
        app(EventHonorService::class)->recalculateSlip($slip);
        $this->assertSame(42500, $slip->fresh()->event_honor);
        $this->assertSame(42500, $slip->fresh()->total_honor);
    }

    public function test_event_note_is_derived_from_entries_and_fits_existing_column(): void
    {
        $teacher = Teacher::factory()->create();
        for ($i = 1; $i <= 3; $i++) {
            [$event] = $this->readyEvent($this->actor, 1, $teacher, str_repeat((string) $i, 100));
            app(EventLifecycleService::class)->complete($event, $this->actor);
        }
        $slip = HonorSlip::sole();
        $this->assertSame(127500, $slip->event_honor);
        $this->assertSame(255, mb_strlen($slip->event_honor_note));
        $this->assertStringStartsWith(str_repeat('1', 100).' | '.str_repeat('2', 100), $slip->event_honor_note);
    }

    public function test_direct_honor_post_enforces_actor(): void
    {
        [$event] = $this->readyEvent($this->actor);
        $service = app(EventHonorService::class);
        $auditor = User::factory()->create()->assignRole('Auditor');
        try {
            $service->post($event, $auditor);
            $this->fail('Auditor tidak berhak posting honor.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_direct_honor_reverse_enforces_actor(): void
    {
        [$event] = $this->readyEvent($this->actor);
        app(EventLifecycleService::class)->complete($event, $this->actor);
        $service = app(EventHonorService::class);
        $auditor = User::factory()->create()->assignRole('Auditor');
        try {
            $service->reverse($event, $auditor, 'Koreksi');
            $this->fail('Auditor tidak berhak membalik honor.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_direct_honor_reverse_requires_reason(): void
    {
        [$event] = $this->readyEvent($this->actor);
        app(EventLifecycleService::class)->complete($event, $this->actor);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(EventHonorService::class)->reverse($event, $this->actor, '  ');
    }

    public function test_recalculation_cannot_modify_a_paid_slip(): void
    {
        $slip = HonorSlip::factory()->create(['status' => 'PAID', 'event_honor' => 42500, 'total_honor' => 42500]);
        try {
            app(EventHonorService::class)->recalculateSlip($slip);
            $this->fail('Slip PAID tidak boleh berubah.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(42500, $slip->fresh()->event_honor);
    }
}
