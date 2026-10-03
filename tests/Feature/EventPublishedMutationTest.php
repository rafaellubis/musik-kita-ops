<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Invoice;
use App\Models\InvoiceComponent;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\EventLifecycleService;
use App\Services\EventParticipantService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventPublishedMutationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private Event $event;

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
        InvoiceComponent::create(['code' => 'MC', 'name' => 'Mini Concert', 'default_price' => 295000, 'is_active' => true]);
    }

    public function test_add_after_publish_updates_existing_unpaid_student_invoice(): void
    {
        $first = $this->addDraft();
        $this->publish();
        $second = Enrollment::factory()->create(['student_id' => $first->student_id]);
        $this->addPublished($second)->assertRedirect();
        $participant = EventParticipant::where('enrollment_id', $second->id)->firstOrFail();
        $this->assertSame($first->fresh()->invoice_id, $participant->invoice_id);
        $this->assertSame(590000, $participant->invoice->total_amount);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame($second->package->price_per_month, $participant->snapshot_package_price);
        $this->assertSame('Pendaftaran tambahan', AuditLog::where('entity_type', 'EventParticipant')->latest('id')->first()->new_values['reason']);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('event_honor_entries', 0);
    }

    public function test_add_or_cancel_is_rejected_when_invoice_has_valid_payment(): void
    {
        $first = $this->addDraft();
        $this->publish();
        $invoice = $first->fresh()->invoice;
        $payment = Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 1000]);
        $second = Enrollment::factory()->create(['student_id' => $first->student_id]);
        $auditCount = AuditLog::count();
        $this->addPublished($second)->assertUnprocessable();
        $this->cancelParticipant($first)->assertUnprocessable();
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertDatabaseCount('event_participants', 1);
        $this->assertSame('ACTIVE', $first->fresh()->status);
        $this->assertSame(295000, $invoice->fresh()->total_amount);
        $payment->update(['voided_at' => now(), 'voided_by' => $this->actor->id]);
        $this->addPublished($second)->assertRedirect();
        $this->cancelParticipant($first)->assertRedirect();
    }

    public function test_cancel_one_enrollment_removes_its_mc_item_and_discount_only(): void
    {
        $first = $this->addDraft();
        $second = $this->addDraft($first->student);
        $this->publish();
        $first->refresh();
        $second->refresh();
        $invoice = $first->invoice;
        $discount = $invoice->items()->create(['parent_item_id' => $first->invoice_item_id, 'item_code' => 'DISKON', 'description' => 'Potongan', 'amount' => -10000]);
        $otherDiscount = $invoice->items()->create(['parent_item_id' => $second->invoice_item_id, 'item_code' => 'DISKON', 'description' => 'Potongan lain', 'amount' => -20000]);
        $otherItem = $invoice->items()->create(['item_code' => 'REG', 'description' => 'Registrasi', 'amount' => 250000]);
        $oldItem = $first->invoice_item_id;
        $this->cancelParticipant($first)->assertRedirect();
        $this->assertDatabaseMissing('invoice_items', ['id' => $oldItem]);
        $this->assertDatabaseMissing('invoice_items', ['id' => $discount->id]);
        $this->assertDatabaseHas('invoice_items', ['id' => $otherDiscount->id]);
        $this->assertDatabaseHas('invoice_items', ['id' => $otherItem->id]);
        $this->assertSame(525000, $invoice->fresh()->total_amount);
        $this->assertSame('UNPAID', $invoice->fresh()->status);
        $this->assertSame('CANCELLED', $first->fresh()->status);
        $this->assertSame($this->actor->id, $first->fresh()->cancelled_by);
        $audit = AuditLog::where('entity_type', 'Invoice')->latest('id')->first();
        $this->assertContains($oldItem, array_column($audit->old_values['items'], 'id'));
        $this->assertContains($discount->id, array_column($audit->old_values['items'], 'id'));
    }

    public function test_cancel_last_enrollment_voids_invoice_and_readding_creates_one_replacement(): void
    {
        $first = $this->addDraft();
        $this->publish();
        $oldInvoice = $first->fresh()->invoice;
        $this->cancelParticipant($first)->assertRedirect();
        $this->assertSame('VOID', $oldInvoice->fresh()->status);
        $this->assertSame('Berhalangan', $oldInvoice->fresh()->voided_reason);
        $this->assertSame($this->actor->id, $oldInvoice->fresh()->voided_by);
        $this->assertNotNull($oldInvoice->fresh()->voided_at);
        $this->addPublished($first->enrollment)->assertRedirect();
        $this->addPublished($first->enrollment)->assertUnprocessable();
        $this->assertDatabaseCount('event_participants', 1);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertSame(1, $this->event->invoices()->notVoid()->count());
        $this->assertNotSame($oldInvoice->id, $first->fresh()->invoice_id);
        $this->assertSame('VOID', $oldInvoice->fresh()->status);
        $this->assertNull($first->fresh()->cancelled_at);
        $audit = AuditLog::where('entity_type', 'EventParticipant')->latest('id')->first();
        $this->assertSame('CANCELLED', $audit->old_values['status']);
        $this->assertSame('ACTIVE', $audit->new_values['status']);
    }

    #[DataProvider('invalidAdditions')]
    public function test_published_addition_revalidates_domain_inputs(string $case): void
    {
        $first = $this->addDraft();
        $this->publish();
        $enrollment = Enrollment::factory()->create(['student_id' => $first->student_id]);
        match ($case) {
            'inactive_teacher' => $enrollment->teacher->update(['is_active' => false]),
            'inactive_enrollment' => $enrollment->update(['status' => 'INACTIVE']),
            'bad_snapshot' => $enrollment->package->update(['duration_min' => 0]),
            'foreign_student' => $enrollment->update(['student_id' => Student::factory()->create()->id]),
        };
        $this->postJson('/events/'.$this->event->id.'/participants', [
            'student_id' => $first->student_id, 'enrollment_ids' => [$enrollment->id], 'reason' => 'Tambahan',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('event_participants', 1);
        $this->assertDatabaseCount('invoice_items', 1);
    }

    public static function invalidAdditions(): array
    {
        return [['inactive_teacher'], ['inactive_enrollment'], ['bad_snapshot'], ['foreign_student']];
    }

    public function test_reschedule_updates_only_unpaid_due_dates_and_audits_change(): void
    {
        $first = $this->addDraft();
        $second = $this->addDraft();
        $third = $this->addDraft();
        $this->publish();
        $second->fresh()->invoice->update(['status' => 'PAID']);
        $third->fresh()->invoice->update(['status' => 'VOID']);
        $this->patchJson($this->url('reschedule'), $this->dates())->assertRedirect();
        $this->assertSame('2027-01-10', $first->fresh()->invoice->due_date->toDateString());
        $this->assertSame('2026-12-10', $second->fresh()->invoice->due_date->toDateString());
        $this->assertSame('2026-12-10', $third->fresh()->invoice->due_date->toDateString());
        $this->assertSame('2027-01-20', $this->event->fresh()->event_date->toDateString());
        $this->assertSame('Jadwal baru', $this->event->fresh()->name);
        $audit = AuditLog::where('entity_type', 'Event')->latest('id')->first();
        $this->assertSame('2026-12-20', $audit->old_values['event_date']);
        $this->assertSame('2027-01-20', $audit->new_values['event_date']);
    }

    #[DataProvider('attendanceCases')]
    public function test_reschedule_is_rejected_after_teacher_or_student_attendance_exists(string $case): void
    {
        $participant = $this->addDraft();
        $this->publish();
        match ($case) {
            'teacher_status' => $participant->activeTeacherAssignment->update(['attendance_status' => 'TIDAK_HADIR']),
            'teacher_timestamp' => $participant->activeTeacherAssignment->update(['confirmed_at' => now()]),
            'student_status' => $participant->update(['attendance_status' => 'HADIR']),
            'student_timestamp' => $participant->update(['attendance_updated_at' => now()]),
        };
        $this->patchJson($this->url('reschedule'), $this->dates())->assertUnprocessable();
        $this->assertSame('2026-12-20', $this->event->fresh()->event_date->toDateString());
    }

    public static function attendanceCases(): array
    {
        return [['teacher_status'], ['teacher_timestamp'], ['student_status'], ['student_timestamp']];
    }

    public function test_cancel_event_requires_voiding_all_valid_payments_first(): void
    {
        $first = $this->addDraft();
        $second = $this->addDraft();
        $this->publish();
        $payment = Payment::factory()->create(['invoice_id' => $second->fresh()->invoice_id, 'amount' => 1000]);
        $this->postJson($this->url('cancel'), ['cancellation_reason' => 'Lokasi tutup'])->assertUnprocessable();
        $this->assertSame('PUBLISHED', $this->event->fresh()->status);
        $this->assertSame('UNPAID', $first->fresh()->invoice->status);
        $payment->update(['voided_at' => now()]);
        $this->postJson($this->url('cancel'), ['cancellation_reason' => 'Lokasi tutup'])->assertRedirect();
        $this->assertSame('CANCELLED', $this->event->fresh()->status);
        $this->assertSame('Lokasi tutup', $this->event->fresh()->cancellation_reason);
        $this->assertSame($this->actor->id, $this->event->fresh()->cancelled_by);
        $this->assertSame(0, $this->event->invoices()->notVoid()->count());
        $this->assertDatabaseCount('invoice_items', 2);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('event_honor_entries', 0);
    }

    public function test_cancel_event_rejects_payment_even_on_void_invoice(): void
    {
        $participant = $this->addDraft();
        $this->publish();
        $invoice = $participant->fresh()->invoice;
        $invoice->update(['status' => 'VOID']);
        Payment::factory()->create(['invoice_id' => $invoice->id]);
        $this->postJson($this->url('cancel'), ['cancellation_reason' => 'Batal'])->assertUnprocessable();
        $this->assertSame('PUBLISHED', $this->event->fresh()->status);
    }

    public function test_mutation_routes_require_owner_or_admin_and_validate_inputs(): void
    {
        $participant = $this->addDraft();
        $this->publish();
        foreach (['Auditor', 'Guru'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role));
            $this->patchJson($this->url('reschedule'), $this->dates())->assertForbidden();
            $this->postJson($this->url('cancel'), ['cancellation_reason' => 'Batal'])->assertForbidden();
        }
        $this->actingAs(User::factory()->create()->assignRole('Owner'));
        $this->patchJson($this->url('reschedule'), array_replace($this->dates(), ['payment_due_date' => '2027-02-01']))->assertUnprocessable();
        $this->postJson($this->url('cancel'), [])->assertUnprocessable();
        $extra = Enrollment::factory()->create(['student_id' => $participant->student_id]);
        $this->postJson('/events/'.$this->event->id.'/participants', ['student_id' => $participant->student_id, 'enrollment_ids' => [$extra->id]])->assertUnprocessable();
        $this->patchJson($this->url('reschedule'), $this->dates())->assertRedirect();
        $this->postJson($this->url('cancel'), ['cancellation_reason' => 'Batal'])->assertRedirect();
    }

    public function test_completed_event_rejects_reschedule_and_cancellation_even_with_stale_model(): void
    {
        $participant = $this->addDraft();
        $this->publish();
        Event::whereKey($this->event->id)->update(['status' => 'COMPLETED']);
        $this->patchJson($this->url('reschedule'), $this->dates())->assertUnprocessable();
        $this->postJson($this->url('cancel'), ['cancellation_reason' => 'Batal'])->assertUnprocessable();
        $this->cancelParticipant($participant)->assertUnprocessable();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(EventLifecycleService::class)->cancel($this->event, $this->actor, 'Batal');
    }

    public function test_payment_reloads_invoice_that_was_voided_after_caller_loaded_it(): void
    {
        $participant = $this->addDraft();
        $this->publish();
        $staleInvoice = $participant->fresh()->invoice;
        $this->cancelParticipant($participant)->assertRedirect();
        try {
            app(PaymentService::class)->recordPayment($staleInvoice, [
                'amount' => 295000, 'method' => 'CASH', 'payment_date' => '2026-12-01',
            ]);
            $this->fail('Invoice yang sudah VOID tidak boleh menerima pembayaran.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('VOID', $exception->getMessage());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('VOID', $staleInvoice->fresh()->status);
    }

    public function test_published_addition_requires_reason_when_service_is_called_directly(): void
    {
        $first = $this->addDraft();
        $this->publish();
        $second = Enrollment::factory()->create(['student_id' => $first->student_id]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(EventParticipantService::class)->addEnrollments($this->event, $second->student, [$second->id], $this->actor, '  ');
    }

    public function test_payment_still_records_full_payment_for_current_unpaid_invoice(): void
    {
        $participant = $this->addDraft();
        $this->publish();
        $invoice = $participant->fresh()->invoice;
        $payment = app(PaymentService::class)->recordPayment($invoice, [
            'amount' => 295000, 'method' => 'CASH', 'payment_date' => '2026-12-01',
        ]);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame(295000, $invoice->fresh()->paid_amount);
        $this->assertSame('PAID', $invoice->fresh()->status);
        $this->cancelParticipant($participant)->assertUnprocessable();
    }

    #[DataProvider('atomicMutations')]
    public function test_mutation_and_financial_changes_roll_back_if_final_audit_fails(string $operation): void
    {
        $first = $this->addDraft();
        $this->addDraft();
        $this->publish();
        $second = Enrollment::factory()->create(['student_id' => $first->student_id]);
        $entity = in_array($operation, ['add', 'participant_cancel']) ? 'EventParticipant' : 'Event';
        $auditCount = AuditLog::count();
        AuditLog::creating(function (AuditLog $audit) use ($entity): void {
            if ($audit->entity_type === $entity) {
                throw new \RuntimeException('Simulasi audit mutasi gagal.');
            }
        });
        try {
            match ($operation) {
                'add' => app(EventParticipantService::class)->addEnrollments($this->event, $second->student, [$second->id], $this->actor, 'Tambahan'),
                'participant_cancel' => app(EventParticipantService::class)->cancelParticipant($first, $this->actor, 'Batal'),
                'reschedule' => app(EventLifecycleService::class)->reschedule($this->event, $this->dates(), $this->actor),
                'event_cancel' => app(EventLifecycleService::class)->cancel($this->event, $this->actor, 'Batal'),
            };
            $this->fail('Mutasi seharusnya gagal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulasi audit mutasi gagal.', $exception->getMessage());
        }
        $this->assertSame('PUBLISHED', $this->event->fresh()->status);
        $this->assertSame('2026-12-20', $this->event->fresh()->event_date->toDateString());
        $this->assertSame('ACTIVE', $first->fresh()->status);
        $this->assertSame(2, $this->event->invoices()->notVoid()->count());
        $this->assertSame(295000, $first->fresh()->invoice->total_amount);
        $this->assertSame('2026-12-10', $first->fresh()->invoice->due_date->toDateString());
        $this->assertDatabaseCount('event_participants', 2);
        $this->assertDatabaseCount('invoice_items', 2);
        $this->assertSame($auditCount, AuditLog::count());
    }

    public function test_reactivating_cancelled_participant_rolls_back_replacement_invoice_if_final_audit_fails(): void
    {
        $participant = $this->addDraft();
        $this->publish();
        $oldInvoice = $participant->fresh()->invoice;
        $this->cancelParticipant($participant)->assertRedirect();
        $cancelled = $participant->fresh();
        $auditCount = AuditLog::count();

        AuditLog::creating(function (AuditLog $audit): void {
            if ($audit->entity_type === 'EventParticipant' && $audit->old_values['status'] === EventParticipant::STATUS_CANCELLED) {
                throw new \RuntimeException('Simulasi audit reaktivasi gagal.');
            }
        });

        try {
            app(EventParticipantService::class)->addEnrollments(
                $this->event,
                $cancelled->student,
                [$cancelled->enrollment_id],
                $this->actor,
                'Aktif kembali',
            );
            $this->fail('Reaktivasi seharusnya gagal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulasi audit reaktivasi gagal.', $exception->getMessage());
        }

        $participant->refresh();
        $this->assertSame(EventParticipant::STATUS_CANCELLED, $participant->status);
        $this->assertSame($cancelled->cancelled_at->toISOString(), $participant->cancelled_at->toISOString());
        $this->assertSame($cancelled->cancelled_by, $participant->cancelled_by);
        $this->assertSame($cancelled->cancellation_reason, $participant->cancellation_reason);
        $this->assertSame($oldInvoice->id, $participant->invoice_id);
        $this->assertNull($participant->invoice_item_id);
        $this->assertSame(Invoice::STATUS_VOID, $oldInvoice->fresh()->status);
        $this->assertSame(0, $this->event->invoices()->notVoid()->count());
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertSame($auditCount, AuditLog::count());
    }

    public static function atomicMutations(): array
    {
        return [['add'], ['participant_cancel'], ['reschedule'], ['event_cancel']];
    }

    private function addDraft(?Student $student = null): EventParticipant
    {
        $enrollment = Enrollment::factory()->create($student ? ['student_id' => $student->id] : []);
        return app(EventParticipantService::class)->addEnrollments($this->event, $enrollment->student, [$enrollment->id], $this->actor)->first();
    }

    private function publish(): void
    {
        app(EventLifecycleService::class)->publish($this->event, $this->actor);
    }

    private function addPublished(Enrollment $enrollment)
    {
        return $this->postJson('/events/'.$this->event->id.'/participants', [
            'student_id' => $enrollment->student_id, 'enrollment_ids' => [$enrollment->id], 'reason' => 'Pendaftaran tambahan',
        ]);
    }

    private function cancelParticipant(EventParticipant $participant)
    {
        return $this->deleteJson('/event-participants/'.$participant->id, ['cancellation_reason' => 'Berhalangan']);
    }

    private function url(string $action): string
    {
        return '/events/'.$this->event->id.'/'.$action;
    }

    private function dates(): array
    {
        return ['name' => 'Jadwal baru', 'event_date' => '2027-01-20', 'payment_due_date' => '2027-01-10', 'notes' => 'Pindah lokasi'];
    }
}
