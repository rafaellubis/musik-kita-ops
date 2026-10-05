<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\HonorSlip;
use App\Models\Invoice;
use App\Models\InvoiceComponent;
use App\Models\InvoiceItem;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\EventParticipantService;
use App\Services\EventInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventPublishTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private Event $event;
    private InvoiceComponent $component;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->actor = User::factory()->create()->assignRole('Admin');
        $this->actingAs($this->actor);
        $this->event = Event::factory()->create([
            'event_date' => '2026-12-20', 'payment_due_date' => '2026-12-10',
        ]);
        $this->component = InvoiceComponent::create([
            'code' => 'MC', 'name' => 'Mini Concert', 'default_price' => 295000, 'is_active' => true,
        ]);
    }

    public function test_publish_creates_one_invoice_per_student_and_one_mc_item_per_enrollment(): void
    {
        $first = $this->addParticipant();
        $second = $this->addParticipant($first->student);
        $third = $this->addParticipant();
        $cancelled = $this->addParticipant();
        $cancelled->update(['status' => 'CANCELLED']);

        $this->post($this->url())->assertRedirect(route('events.show', $this->event));

        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('invoice_items', 3);
        $invoice = $first->fresh()->invoice;
        $this->assertSame($invoice->id, $second->fresh()->invoice_id);
        $this->assertNotSame($invoice->id, $third->fresh()->invoice_id);
        $this->assertSame(590000, $invoice->total_amount);
        $this->assertNull($invoice->enrollment_id);
        $this->assertSame('2026-12-10', $invoice->due_date->toDateString());
        $this->assertNull($cancelled->fresh()->invoice_id);
        foreach ([$first->fresh(), $second->fresh()] as $participant) {
            $item = $participant->invoiceItem;
            $this->assertSame('MC', $item->item_code);
            $this->assertSame(295000, $item->amount);
            $this->assertSame(295000, $participant->snapshot_mc_fee);
            $this->assertSame($this->component->id, $item->invoice_component_id);
            $this->assertSame($this->event->id, $item->metadata['event_id']);
            $this->assertSame($participant->id, $item->metadata['event_participant_id']);
            $this->assertSame($participant->enrollment_id, $item->metadata['enrollment_id']);
            $this->assertSame($participant->snapshot_package_code, $item->metadata['package_snapshot']['code']);
        }
        $this->event->refresh();
        $this->assertSame('PUBLISHED', $this->event->status);
        $this->assertNotNull($this->event->published_at);
        $this->assertSame($this->actor->id, $this->event->published_by);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'Event', 'entity_id' => $this->event->id,
            'action' => 'UPDATE', 'user_id' => $this->actor->id,
        ]);
        $this->assertSame(2, AuditLog::where('entity_type', 'Invoice')->count());
    }

    public function test_publish_uses_active_mc_component_default_price(): void
    {
        $participant = $this->addParticipant();
        $participant->enrollment->package->update(['price_per_month' => 900000]);
        $this->component->update(['default_price' => 312000]);
        $this->post($this->url())->assertRedirect();
        $this->assertSame(312000, $participant->fresh()->invoiceItem->amount);
        $this->assertSame($participant->snapshot_package_price, $participant->fresh()->snapshot_package_price);
    }

    #[DataProvider('invalidComponents')]
    public function test_publish_rejects_missing_inactive_or_invalid_mc_component(string $case): void
    {
        $this->addParticipant();
        match ($case) {
            'missing' => $this->component->delete(),
            'inactive' => $this->component->update(['is_active' => false]),
            'zero' => $this->component->update(['default_price' => 0]),
        };
        $this->postJson($this->url())->assertUnprocessable();
        $this->assertUnpublished();
    }

    public static function invalidComponents(): array
    {
        return [['missing'], ['inactive'], ['zero']];
    }

    public function test_publish_rejects_paid_final_teacher_slip_for_event_month(): void
    {
        $participant = $this->addParticipant();
        $replacement = Teacher::factory()->create(['is_active' => true]);
        app(EventParticipantService::class)->assignDraftTeacher($participant, $replacement, $this->actor);
        HonorSlip::factory()->create([
            'teacher_id' => $replacement->id, 'year' => 2026, 'month' => 12, 'status' => 'PAID',
        ]);
        $this->postJson($this->url())->assertUnprocessable();
        $this->assertUnpublished();
    }

    public function test_paid_slip_for_another_month_or_previous_teacher_does_not_block_publish(): void
    {
        $participant = $this->addParticipant();
        $replacement = Teacher::factory()->create(['is_active' => true]);
        app(EventParticipantService::class)->assignDraftTeacher($participant, $replacement, $this->actor);
        HonorSlip::factory()->create([
            'teacher_id' => $participant->enrollment->teacher_id, 'year' => 2026, 'month' => 12, 'status' => 'PAID',
        ]);
        HonorSlip::factory()->create([
            'teacher_id' => $replacement->id, 'year' => 2026, 'month' => 11, 'status' => 'PAID',
        ]);
        $this->post($this->url())->assertRedirect();
        $this->assertSame('PUBLISHED', $this->event->fresh()->status);
    }

    public function test_publish_rolls_back_all_invoices_when_one_student_fails(): void
    {
        $first = $this->addParticipant();
        $second = $this->addParticipant();
        $created = 0;
        InvoiceItem::creating(function (InvoiceItem $item) use ($second, &$created): void {
            if ($item->metadata['event_participant_id'] === $second->id) {
                throw new \RuntimeException('Simulasi item murid kedua gagal.');
            }
            $created++;
        });
        $this->withoutExceptionHandling();
        try {
            $this->post($this->url());
            $this->fail('Publikasi seharusnya gagal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulasi item murid kedua gagal.', $exception->getMessage());
        }
        $this->assertSame(1, $created);
        $this->assertUnpublished();
        $this->assertNull($first->fresh()->invoice_id);
        $this->assertSame(0, $first->fresh()->snapshot_mc_fee);
        $this->assertSame(0, AuditLog::whereIn('entity_type', ['Invoice', 'Event'])->count());
    }

    public function test_double_publish_request_does_not_duplicate_invoice_or_items(): void
    {
        $this->addParticipant();
        $this->post($this->url())->assertRedirect();
        $publishedAt = $this->event->fresh()->published_at;
        $auditCount = AuditLog::count();
        $this->component->update(['is_active' => false, 'default_price' => 400000]);
        $this->post($this->url())->assertRedirect();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_items', 1);
        $this->assertSame(295000, Invoice::first()->total_amount);
        $this->assertEquals($publishedAt, $this->event->fresh()->published_at);
        $this->assertSame($auditCount, AuditLog::count());
    }

    #[DataProvider('invalidPublishedStates')]
    public function test_published_retry_rejects_inconsistent_data(string $case): void
    {
        $participant = $this->addParticipant();
        $this->post($this->url())->assertRedirect();
        $participant->refresh();
        match ($case) {
            'missing_link' => $participant->update(['invoice_item_id' => null]),
            'wrong_amount' => $participant->invoiceItem->update(['amount' => 1]),
            'wrong_metadata' => $participant->invoiceItem->update(['metadata' => []]),
            'wrong_total' => $participant->invoice->update(['total_amount' => 1]),
            'wrong_due_date' => $participant->invoice->update(['due_date' => '2026-12-21']),
            'wrong_component' => $participant->invoiceItem->update(['invoice_component_id' => InvoiceComponent::create([
                'code' => 'REG', 'name' => 'Registrasi', 'default_price' => 250000, 'is_active' => true,
            ])->id]),
            'grouped_invoice_has_enrollment' => $participant->invoice->update(['enrollment_id' => $participant->enrollment_id]),
            'extra_manual_item' => tap($participant->invoice->items()->create([
                'item_code' => 'REG', 'description' => 'Item manual asing', 'amount' => 25000,
                'added_by' => $this->actor->id,
            ]), fn () => $participant->invoice->increment('total_amount', 25000)),
            'duplicate_invoice' => Invoice::factory()->create(['event_id' => $this->event->id, 'student_id' => $participant->student_id]),
        };
        $this->postJson($this->url())->assertUnprocessable();
    }

    public static function invalidPublishedStates(): array
    {
        return [
            ['missing_link'], ['wrong_amount'], ['wrong_metadata'], ['wrong_total'], ['wrong_due_date'],
            ['wrong_component'], ['grouped_invoice_has_enrollment'], ['extra_manual_item'], ['duplicate_invoice'],
        ];
    }

    public function test_retry_accepts_metadata_key_order_normalized_by_mysql_json(): void
    {
        $participant = $this->addParticipant();
        $this->post($this->url())->assertRedirect();
        $item = $participant->fresh()->invoiceItem;
        $metadata = $item->metadata;
        ksort($metadata['package_snapshot']);
        ksort($metadata);
        $item->update(['metadata' => $metadata]);

        $this->post($this->url())->assertRedirect();
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_publish_rolls_back_invoices_and_status_when_publication_audit_fails(): void
    {
        $participant = $this->addParticipant();
        AuditLog::creating(function (AuditLog $audit): void {
            if ($audit->entity_type === 'Event') {
                throw new \RuntimeException('Simulasi audit publikasi gagal.');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post($this->url());
            $this->fail('Publikasi seharusnya gagal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulasi audit publikasi gagal.', $exception->getMessage());
        }
        $this->assertUnpublished();
        $this->assertNull($participant->fresh()->invoice_id);
        $this->assertSame(0, AuditLog::where('entity_type', 'Invoice')->count());
    }

    #[DataProvider('invalidDraftStates')]
    public function test_publish_revalidates_draft_prerequisites(string $case): void
    {
        $participant = $this->addParticipant();
        match ($case) {
            'empty' => $participant->update(['status' => 'CANCELLED']),
            'inactive_enrollment' => $participant->enrollment->update(['status' => 'INACTIVE']),
            'foreign_student' => $participant->update(['student_id' => Student::factory()->create()->id]),
            'missing_assignment' => $participant->update(['active_teacher_assignment_id' => null]),
            'foreign_assignment' => $participant->update(['active_teacher_assignment_id' => EventTeacherAssignment::factory()->create()->id]),
            'inactive_teacher' => $participant->activeTeacherAssignment->teacher->update(['is_active' => false]),
            'bad_snapshot' => $participant->update(['snapshot_package_code' => '']),
            'bad_honor' => $participant->update(['snapshot_honor_amount' => 1]),
            'bad_date' => $this->event->update(['payment_due_date' => '2026-12-21']),
            'reserved_type' => $this->event->update(['type' => 'UJIAN_GRADE']),
            'cancelled_event' => $this->event->update(['status' => 'CANCELLED']),
        };
        $this->postJson($this->url())->assertUnprocessable();
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertNull($this->event->fresh()->published_at);
    }

    public static function invalidDraftStates(): array
    {
        return array_map(fn ($case) => [$case], [
            'empty', 'inactive_enrollment', 'foreign_student', 'missing_assignment', 'foreign_assignment',
            'inactive_teacher', 'bad_snapshot', 'bad_honor', 'bad_date', 'reserved_type', 'cancelled_event',
        ]);
    }

    public function test_conflicting_invoice_blocks_draft_but_void_history_is_preserved(): void
    {
        $participant = $this->addParticipant();
        $existing = Invoice::factory()->create(['event_id' => $this->event->id, 'student_id' => $participant->student_id]);
        $this->postJson($this->url())->assertUnprocessable();
        $this->assertSame('DRAFT', $this->event->fresh()->status);
        $existing->update(['status' => 'VOID']);
        $this->post($this->url())->assertRedirect();
        $this->assertSame('VOID', $existing->fresh()->status);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertNotSame($existing->id, $participant->fresh()->invoice_id);
    }

    public function test_only_owner_and_admin_can_publish(): void
    {
        $this->addParticipant();
        foreach (['Auditor', 'Guru'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role))
                ->post($this->url())->assertForbidden();
        }
        $this->assertUnpublished();
        $this->actingAs(User::factory()->create()->assignRole('Owner'))
            ->post($this->url())->assertRedirect();
        $this->assertSame('PUBLISHED', $this->event->fresh()->status);
    }

    public function test_add_published_participant_appends_item_to_existing_student_invoice(): void
    {
        $first = $this->addParticipant();
        $second = $this->addParticipant($first->student);
        $second->update(['status' => 'CANCELLED']);
        $this->post($this->url())->assertRedirect();
        $second->update(['status' => 'ACTIVE']);

        $invoice = app(EventInvoiceService::class)->addPublishedParticipant($second, $this->actor);

        $this->assertSame($first->fresh()->invoice_id, $invoice->id);
        $this->assertSame(590000, $invoice->total_amount);
        $this->assertSame(2, $invoice->items()->count());
        $this->assertSame(295000, $second->fresh()->snapshot_mc_fee);
    }

    public function test_cancel_published_participant_removes_only_its_item_and_recalculates_invoice(): void
    {
        $first = $this->addParticipant();
        $second = $this->addParticipant($first->student);
        $this->post($this->url())->assertRedirect();

        app(EventInvoiceService::class)->cancelPublishedParticipant($second, $this->actor, 'Murid berhalangan.');

        $this->assertSame('CANCELLED', $second->fresh()->status);
        $this->assertSame('Murid berhalangan.', $second->fresh()->cancellation_reason);
        $this->assertNull($second->fresh()->invoice_item_id);
        $this->assertSame(295000, $first->fresh()->invoice->total_amount);
        $this->assertSame('UNPAID', $first->fresh()->invoice->status);
        $this->assertDatabaseCount('invoice_items', 1);
    }

    private function addParticipant(?Student $student = null): EventParticipant
    {
        $enrollment = Enrollment::factory()->create($student ? ['student_id' => $student->id] : []);
        return app(EventParticipantService::class)->addEnrollments(
            $this->event, $enrollment->student, [$enrollment->id], $this->actor,
        )->first();
    }

    private function url(): string
    {
        return '/events/'.$this->event->id.'/publish';
    }

    private function assertUnpublished(): void
    {
        $this->assertSame('DRAFT', $this->event->fresh()->status);
        $this->assertNull($this->event->fresh()->published_at);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }
}
