<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventHonorEntry;
use App\Models\EventTeacherAssignment;
use App\Models\HonorSlip;
use App\Models\Invoice;
use App\Models\Teacher;
use App\Models\User;
use App\Services\EventAttendanceService;
use App\Services\EventLifecycleService;
use App\Services\HonorCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesEventHonorScenario;
use Tests\TestCase;

class EventCompletionHonorTest extends TestCase
{
    use RefreshDatabase, CreatesEventHonorScenario;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 20));
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->actor = User::factory()->create()->assignRole('Owner');
        $this->actingAs($this->actor);
    }

    #[DataProvider('incompleteStates')]
    public function test_complete_requires_all_active_teachers_and_participants_complete(string $case): void
    {
        [$event, $participants] = $this->readyEvent($this->actor);
        $participant = $participants->first();
        match ($case) {
            'teacher_pending' => $participant->activeTeacherAssignment->update(['attendance_status' => 'PENDING']),
            'teacher_absent' => $participant->activeTeacherAssignment->update(['attendance_status' => 'TIDAK_HADIR']),
            'student_pending' => $participant->update(['attendance_status' => 'PENDING']),
            'no_active_participant' => $participant->update(['status' => 'CANCELLED']),
            'draft' => $event->update(['status' => 'DRAFT']),
            'cancelled' => $event->update(['status' => 'CANCELLED']),
        };
        $this->postJson('/events/'.$event->id.'/complete', ['force' => true])->assertUnprocessable();
        $this->assertDatabaseCount('event_honor_entries', 0);
        $this->assertDatabaseCount('teacher_honor_slips', 0);
    }

    public static function incompleteStates(): array
    {
        return array_map(fn ($case) => [$case], ['teacher_pending', 'teacher_absent', 'student_pending', 'no_active_participant', 'draft', 'cancelled']);
    }

    public function test_present_teacher_gets_snapshot_honor_even_when_student_absent(): void
    {
        [$event, $participants] = $this->readyEvent($this->actor);
        $participant = $participants->first();
        $participant->enrollment->package->update(['price_per_month' => 900000]);
        app(EventAttendanceService::class)->recordParticipant($participant, 'TIDAK_HADIR', null, $this->actor);
        $this->post('/events/'.$event->id.'/complete')->assertRedirect(route('events.show', $event));
        $entry = EventHonorEntry::sole();
        $this->assertSame(42500, $entry->amount);
        $this->assertSame(340000, $entry->snapshot_package_price);
        $this->assertSame($participant->snapshot_package_code, $entry->snapshot_package_code);
        $this->assertSame($participant->snapshot_package_id, $entry->snapshot_package_id);
        $this->assertSame('package_price * 0.5 / 4', $entry->honor_formula);
        $this->assertSame($participant->activeTeacherAssignment->teacher_id, $entry->teacher_id);
        $this->assertSame($this->actor->id, $entry->posted_by);
        $this->assertNotNull($entry->posted_at);
        $this->assertSame(42500, $entry->honorSlip->event_honor);
        $this->assertSame('Konser KITA Juni', $entry->honorSlip->event_honor_note);
        $this->assertSame('COMPLETED', $event->fresh()->status);
        $this->assertSame($this->actor->id, $event->fresh()->completed_by);
        $this->assertDatabaseCount('class_sessions', 0);
    }

    public function test_replacement_teacher_receives_honor_and_original_teacher_gets_zero(): void
    {
        [$event, $participants] = $this->readyEvent($this->actor);
        $participant = $participants->first();
        $original = $participant->activeTeacherAssignment;
        $replacement = Teacher::factory()->create();
        $attendance = app(EventAttendanceService::class);
        $attendance->resetParticipant($participant, $this->actor, 'Koreksi pendamping');
        $participant = $attendance->reassignParticipant($participant, $replacement, $this->actor, 'Guru pengganti');
        $original->update(['attendance_status' => 'PENDING']); // Assignment historis tidak menghalangi.
        $attendance->confirmTeacher($participant->activeTeacherAssignment, 'HADIR', $this->actor, null);
        $attendance->recordParticipant($participant, 'HADIR', null, $this->actor);
        $this->post('/events/'.$event->id.'/complete')->assertRedirect();
        $this->assertSame($replacement->id, EventHonorEntry::sole()->teacher_id);
        $this->assertSame(0, EventHonorEntry::where('teacher_id', $original->teacher_id)->count());
        $this->assertSame(0, HonorSlip::where('teacher_id', $original->teacher_id)->sum('event_honor'));
    }

    public function test_double_complete_creates_one_active_entry_per_participant(): void
    {
        [$event] = $this->readyEvent($this->actor, 2);
        $service = app(EventLifecycleService::class);
        $service->complete($event, $this->actor);
        $auditCount = AuditLog::count();
        $entries = EventHonorEntry::orderBy('id')->get()->toArray();
        $service->complete($event, $this->actor); // Model lama, simulasi retry request.
        $this->assertSame($entries, EventHonorEntry::orderBy('id')->get()->toArray());
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertSame(85000, HonorSlip::sole()->event_honor);
        $this->assertSame(85000, HonorSlip::sole()->total_honor);
    }

    public function test_complete_rolls_back_status_when_honor_posting_fails(): void
    {
        [$event] = $this->readyEvent($this->actor, 2);
        $created = 0;
        EventHonorEntry::creating(function () use (&$created): void {
            if (++$created === 2) {
                throw new \RuntimeException('Posting kedua gagal');
            }
        });
        $auditCount = AuditLog::count();
        try {
            app(EventLifecycleService::class)->complete($event, $this->actor);
            $this->fail('Completion harus dibatalkan seluruhnya.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Posting kedua gagal', $exception->getMessage());
        }
        $this->assertSame(2, $created);
        $this->assertSame('PUBLISHED', $event->fresh()->status);
        $this->assertNull($event->fresh()->completed_at);
        $this->assertDatabaseCount('event_honor_entries', 0);
        $this->assertDatabaseCount('teacher_honor_slips', 0);
        $this->assertSame($auditCount, AuditLog::count());
    }

    public function test_unpaid_event_invoice_does_not_block_attendance_or_completion(): void
    {
        [$event, $participants] = $this->readyEvent($this->actor);
        $this->assertSame('UNPAID', $participants->first()->invoice->status);
        $this->assertSame('HADIR', $participants->first()->attendance_status);
        $this->post('/events/'.$event->id.'/complete')->assertRedirect();
        $this->assertSame('COMPLETED', $event->fresh()->status);
        $this->assertSame(0, $participants->first()->invoice->paid_amount);
    }

    #[DataProvider('invalidOwnership')]
    public function test_completion_rejects_inconsistent_assignment_invoice_and_item_ownership(string $case): void
    {
        [$event, $participants] = $this->readyEvent($this->actor);
        $participant = $participants->first();
        match ($case) {
            'assignment' => $participant->update(['active_teacher_assignment_id' => EventTeacherAssignment::factory()->create()->id]),
            'invoice_event' => $participant->invoice->update(['event_id' => Event::factory()->create()->id]),
            'invoice_student' => $participant->invoice->update(['student_id' => \App\Models\Student::factory()->create()->id]),
            'invoice_enrollment' => $participant->invoice->update(['enrollment_id' => $participant->enrollment_id]),
            'invoice_total' => $participant->invoice->update(['total_amount' => 1]),
            'item_invoice' => $participant->invoiceItem->update(['invoice_id' => Invoice::factory()->create()->id]),
            'item_amount' => $participant->invoiceItem->update(['amount' => 1]),
            'item_participant' => $participant->invoiceItem->update(['metadata' => ['event_participant_id' => 99999]]),
            'item_snapshot' => $participant->invoiceItem->update(['metadata' => [
                'event_id' => $event->id,
                'event_participant_id' => $participant->id,
                'enrollment_id' => $participant->enrollment_id,
            ]]),
            'mc_fee_snapshot' => $participant->update(['snapshot_mc_fee' => 1]),
            'missing_item' => $participant->update(['invoice_item_id' => null]),
            'void_invoice' => $participant->invoice->update(['status' => 'VOID']),
        };
        $this->postJson('/events/'.$event->id.'/complete')->assertUnprocessable();
        $this->assertSame('PUBLISHED', $event->fresh()->status);
        $this->assertDatabaseCount('event_honor_entries', 0);
    }

    public static function invalidOwnership(): array
    {
        return array_map(fn ($case) => [$case], [
            'assignment', 'invoice_event', 'invoice_student', 'invoice_enrollment', 'invoice_total',
            'item_invoice', 'item_amount', 'item_participant', 'item_snapshot', 'mc_fee_snapshot',
            'missing_item', 'void_invoice',
        ]);
    }

    public function test_reopen_reverses_entries_and_recalculates_unpaid_slips(): void
    {
        [$event, $participants] = $this->readyEvent($this->actor);
        [$other] = $this->readyEvent($this->actor, 1, $participants->first()->activeTeacherAssignment->teacher, 'Konser Kedua');
        $service = app(EventLifecycleService::class);
        $service->complete($event, $this->actor);
        $service->complete($other, $this->actor);
        $slip = HonorSlip::sole();
        $slip->update(['base_honor' => 100000, 'transport_honor' => 10000, 'other_honor' => 5000, 'other_honor_note' => 'Bonus']);
        $entry = $event->honorEntries()->sole();
        $this->post('/events/'.$event->id.'/reopen', ['reason' => 'Koreksi pendamping'])->assertRedirect();
        $this->assertSame('PUBLISHED', $event->fresh()->status);
        $this->assertSame('REVERSED', $entry->fresh()->status);
        $this->assertSame($this->actor->id, $entry->fresh()->reversed_by);
        $this->assertSame('Koreksi pendamping', $entry->fresh()->reversal_reason);
        $this->assertNotNull($entry->fresh()->reversed_at);
        $this->assertSame(42500, $slip->fresh()->event_honor);
        $this->assertSame('Konser Kedua', $slip->fresh()->event_honor_note);
        $this->assertSame(157500, $slip->fresh()->total_honor);
        $audit = AuditLog::where('entity_type', 'Event')->latest('id')->first();
        $this->assertSame('Koreksi pendamping', $audit->new_values['reason']);
        $this->assertSame($this->actor->id, $audit->user_id);
        $service->complete($event, $this->actor);
        $this->assertSame('POSTED', $entry->fresh()->status);
        $this->assertSame(85000, $slip->fresh()->event_honor);
        $this->assertSame(200000, $slip->fresh()->total_honor);
        $this->assertDatabaseCount('event_honor_entries', 2);
        $this->assertTrue(AuditLog::where('entity_type', 'EventHonorEntry')->where('entity_id', $entry->id)->count() >= 3);
    }

    public function test_recompletion_reuses_entry_after_teacher_changes_and_preserves_reversal_history(): void
    {
        [$event, $participants] = $this->readyEvent($this->actor);
        $service = app(EventLifecycleService::class);
        $service->complete($event, $this->actor);
        $entry = EventHonorEntry::sole();
        $oldSlip = $entry->honorSlip;
        $service->reopen($event, $this->actor, 'Guru salah');
        $attendance = app(EventAttendanceService::class);
        $participant = $attendance->resetParticipant($participants->first(), $this->actor, 'Guru salah');
        $replacement = Teacher::factory()->create();
        $participant = $attendance->reassignParticipant($participant, $replacement, $this->actor, 'Guru salah');
        $attendance->confirmTeacher($participant->activeTeacherAssignment, 'HADIR', $this->actor, null);
        $attendance->recordParticipant($participant, 'HADIR', null, $this->actor);
        $service->complete($event, $this->actor);
        $this->assertDatabaseCount('event_honor_entries', 1);
        $this->assertSame($replacement->id, $entry->fresh()->teacher_id);
        $this->assertSame(0, $oldSlip->fresh()->event_honor);
        $this->assertSame(42500, $entry->fresh()->honorSlip->event_honor);
        $this->assertTrue(AuditLog::where('entity_type', 'EventHonorEntry')->get()->contains(
            fn ($audit) => ($audit->new_values['reversal_reason'] ?? null) === 'Guru salah'
        ));
    }

    public function test_reopen_is_owner_only_and_rejected_for_paid_slip(): void
    {
        [$event] = $this->readyEvent($this->actor);
        app(EventLifecycleService::class)->complete($event, $this->actor);
        foreach (['Admin', 'Auditor', 'Guru'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role))
                ->postJson('/events/'.$event->id.'/reopen', ['reason' => 'Koreksi'])->assertForbidden();
        }
        $this->actingAs($this->actor)->postJson('/events/'.$event->id.'/reopen', ['reason' => ' '])->assertUnprocessable();
        HonorSlip::sole()->update(['status' => 'PAID']);
        $this->postJson('/events/'.$event->id.'/reopen', ['reason' => 'Koreksi'])->assertUnprocessable();
        $this->assertSame('COMPLETED', $event->fresh()->status);
        $this->assertSame('POSTED', EventHonorEntry::sole()->status);
    }

    public function test_reopen_and_completion_enforce_roles_for_direct_service_calls(): void
    {
        [$event] = $this->readyEvent($this->actor);
        $service = app(EventLifecycleService::class);
        foreach (['Auditor', 'Guru'] as $role) {
            $actor = User::factory()->create()->assignRole($role);
            try {
                $service->complete($event, $actor);
                $this->fail('Role tidak berhak melakukan completion.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $service->complete($event, $this->actor);
        try {
            $service->reopen($event, User::factory()->create()->assignRole('Admin'), 'Koreksi');
            $this->fail('Reopen hanya untuk Owner.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_reopen_rolls_back_reversal_when_audit_fails(): void
    {
        [$event] = $this->readyEvent($this->actor);
        $service = app(EventLifecycleService::class);
        $service->complete($event, $this->actor);
        AuditLog::creating(function (AuditLog $audit): void {
            if ($audit->entity_type === 'Event') {
                throw new \RuntimeException('Audit gagal');
            }
        });
        try {
            $service->reopen($event, $this->actor, 'Koreksi');
            $this->fail('Reopen harus batal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit gagal', $exception->getMessage());
        }
        $this->assertSame('COMPLETED', $event->fresh()->status);
        $this->assertSame('POSTED', EventHonorEntry::sole()->status);
        $this->assertSame(42500, HonorSlip::sole()->event_honor);
    }

    public function test_mark_paid_is_blocked_while_same_month_event_is_published(): void
    {
        $slip = HonorSlip::factory()->create(['year' => 2026, 'month' => 6, 'status' => 'CALCULATED']);
        Event::factory()->published()->create(['event_date' => '2026-06-25']);
        $this->post(route('honors.mark-paid', $slip))->assertRedirect()->assertSessionHas('error');
        $this->assertSame('CALCULATED', $slip->fresh()->status);
        $this->expectException(\DomainException::class);
        app(HonorCalculationService::class)->markPaid($slip, $this->actor->id);
    }

    public function test_cancelled_event_no_longer_blocks_mark_paid(): void
    {
        $slip = HonorSlip::factory()->create(['year' => 2026, 'month' => 6, 'status' => 'CALCULATED']);
        Event::factory()->cancelled()->create(['event_date' => '2026-06-25']);
        Event::factory()->completed()->create(['event_date' => '2026-06-20']);
        Event::factory()->published()->create(['event_date' => '2025-06-25']);
        Event::factory()->published()->create(['event_date' => '2026-07-25']);
        $this->post(route('honors.mark-paid', $slip))->assertRedirect()->assertSessionHas('success');
        $this->assertSame('PAID', $slip->fresh()->status);
    }

    public function test_monthly_recalculation_preserves_derived_event_honor(): void
    {
        [$event] = $this->readyEvent($this->actor);
        app(EventLifecycleService::class)->complete($event, $this->actor);
        $slip = HonorSlip::sole();
        $slip->update(['event_honor' => 1, 'event_honor_note' => 'Tidak sah', 'transport_honor' => 10000, 'other_honor' => 5000]);
        app(HonorCalculationService::class)->calculateForTeacher($slip->teacher, 2026, 6, $this->actor->id);
        $this->assertSame(42500, $slip->fresh()->event_honor);
        $this->assertSame('Konser KITA Juni', $slip->fresh()->event_honor_note);
        $this->assertSame(57500, $slip->fresh()->total_honor);
        $this->assertSame('CALCULATED', $slip->fresh()->status);
    }
}
