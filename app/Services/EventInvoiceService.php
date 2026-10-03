<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Invoice;
use App\Models\InvoiceComponent;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EventInvoiceService
{
    public function __construct(private InvoiceService $invoiceService) {}

    /** Dipanggil lifecycle dalam transaksi yang sama dengan perubahan status event. */
    public function publishInvoices(Event $event, User $actor): Collection
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Invoice publikasi harus dibuat dalam transaksi lifecycle event.');
        }
        $event = Event::query()->lockForUpdate()->findOrFail($event->id);
        abort_unless($event->isDraft() || $event->isPublished(), 422, 'Status event tidak mengizinkan publikasi invoice.');
        $participants = $event->participants()->where('status', EventParticipant::STATUS_ACTIVE)
            ->orderBy('id')->lockForUpdate()->get();
        $invoices = $event->invoices()->orderBy('id')->lockForUpdate()->get()->where('status', '!=', Invoice::STATUS_VOID);

        if ($event->isPublished()) {
            $this->assertConsistent($event, $participants, $invoices);
            return $invoices;
        }

        abort_if($invoices->isNotEmpty() || $participants->contains(fn ($participant) =>
            $participant->invoice_id || $participant->invoice_item_id), 422, 'Event DRAFT sudah memiliki invoice atau tautan invoice yang bertentangan.');
        $component = $this->activeComponent();
        $created = new Collection();
        foreach ($participants->groupBy('student_id') as $studentId => $group) {
            $invoice = $this->createInvoice($event, $studentId);
            foreach ($group as $participant) {
                $this->addItem($invoice, $participant, $component);
            }
            $this->recalculate($invoice);
            $this->audit($invoice, $actor, AuditLog::ACTION_CREATE, null, $invoice->fresh('items')->toArray());
            $created->push($invoice);
        }

        return $created;
    }

    public function addPublishedParticipant(EventParticipant $participant, User $actor): Invoice
    {
        return DB::transaction(function () use ($participant, $actor) {
            $event = Event::query()->lockForUpdate()->findOrFail($participant->event_id);
            abort_unless($event->isPublished(), 422, 'Penambahan invoice peserta hanya tersedia pada event PUBLISHED.');
            $participant = EventParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            abort_unless($participant->event_id === $event->id && $participant->status === EventParticipant::STATUS_ACTIVE,
                422, 'Peserta harus aktif pada event ini.');
            abort_if($participant->invoice_id || $participant->invoice_item_id, 422, 'Peserta sudah memiliki tagihan.');
            $invoices = $event->invoices()->where('student_id', $participant->student_id)
                ->notVoid()->lockForUpdate()->get();
            abort_if($invoices->count() > 1, 422, 'Murid memiliki lebih dari satu invoice aktif pada event ini.');
            $component = $this->activeComponent();
            $invoice = $invoices->first();
            $old = $invoice?->toArray();
            if ($invoice) {
                $this->assertUnpaid($invoice);
            } else {
                $invoice = $this->createInvoice($event, $participant->student_id);
            }
            $this->addItem($invoice, $participant, $component);
            $this->recalculate($invoice);
            $this->audit($invoice, $actor, $old ? AuditLog::ACTION_UPDATE : AuditLog::ACTION_CREATE,
                $old, $invoice->fresh('items')->toArray());

            return $invoice;
        });
    }

    public function cancelPublishedParticipant(EventParticipant $participant, User $actor, string $reason): void
    {
        abort_if(blank($reason), 422, 'Alasan pembatalan peserta wajib diisi.');
        DB::transaction(function () use ($participant, $actor, $reason) {
            $event = Event::query()->lockForUpdate()->findOrFail($participant->event_id);
            abort_unless($event->isPublished(), 422, 'Pembatalan invoice peserta hanya tersedia pada event PUBLISHED.');
            $participant = EventParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            abort_unless($participant->event_id === $event->id && $participant->status === EventParticipant::STATUS_ACTIVE,
                422, 'Peserta harus aktif pada event ini.');
            $invoices = $event->invoices()->where('student_id', $participant->student_id)->notVoid()->lockForUpdate()->get();
            abort_unless($invoices->count() === 1 && $invoices->first()->id === $participant->invoice_id,
                422, 'Invoice peserta tidak konsisten.');
            $invoice = $invoices->first();
            $this->assertUnpaid($invoice);
            $items = $invoice->items()->lockForUpdate()->get();
            $item = $items->firstWhere('id', $participant->invoice_item_id);
            abort_unless($item && $item->item_code === 'MC'
                && ($item->metadata['event_participant_id'] ?? null) === $participant->id,
                422, 'Item tagihan peserta tidak konsisten.');
            $old = $invoice->toArray() + ['items' => $items->toArray()];
            InvoiceItem::where('parent_item_id', $item->id)->delete();
            $participant->update([
                'status' => EventParticipant::STATUS_CANCELLED, 'cancelled_at' => now(),
                'cancelled_by' => $actor->id, 'cancellation_reason' => $reason,
                'invoice_item_id' => null,
            ]);
            $item->delete();
            $this->recalculate($invoice);
            if (! $invoice->items()->exists()) {
                $invoice->update([
                    'status' => Invoice::STATUS_VOID, 'voided_at' => now(),
                    'voided_by' => $actor->id, 'voided_reason' => $reason,
                ]);
            }
            $this->audit($invoice, $actor, $invoice->status === Invoice::STATUS_VOID ? AuditLog::ACTION_VOID : AuditLog::ACTION_UPDATE,
                $old, $invoice->fresh('items')->toArray() + ['reason' => $reason, 'cancelled_participant_id' => $participant->id]);
        });
    }

    private function activeComponent(): InvoiceComponent
    {
        $component = InvoiceComponent::where('code', 'MC')->lockForUpdate()->first();
        abort_unless($component && $component->is_active && $component->default_price > 0,
            422, 'Komponen MC harus tersedia, aktif, dan memiliki nominal lebih dari nol.');
        return $component;
    }

    private function createInvoice(Event $event, int $studentId): Invoice
    {
        return Invoice::create([
            'invoice_number' => $this->invoiceService->generateNumber(now()->year, now()->month),
            'event_id' => $event->id, 'student_id' => $studentId, 'enrollment_id' => null,
            'year' => now()->year, 'month' => now()->month,
            'description' => 'Mini Concert — '.$event->name,
            'total_amount' => 0, 'paid_amount' => 0, 'status' => Invoice::STATUS_UNPAID,
            'due_date' => $event->payment_due_date, 'issued_at' => today(),
            'payment_mode' => Invoice::MODE_FULL,
        ]);
    }

    private function addItem(Invoice $invoice, EventParticipant $participant, InvoiceComponent $component): void
    {
        $item = $invoice->items()->create([
            'invoice_component_id' => $component->id, 'item_code' => 'MC',
            'description' => 'Mini Concert — '.$participant->snapshot_package_code,
            'amount' => $component->default_price, 'metadata' => $this->metadata($participant),
        ]);
        $participant->update([
            'invoice_id' => $invoice->id, 'invoice_item_id' => $item->id,
            'snapshot_mc_fee' => $component->default_price,
        ]);
    }

    private function metadata(EventParticipant $participant): array
    {
        return [
            'event_id' => $participant->event_id, 'event_participant_id' => $participant->id,
            'enrollment_id' => $participant->enrollment_id,
            'package_snapshot' => [
                'id' => $participant->snapshot_package_id, 'code' => $participant->snapshot_package_code,
                'instrument_name' => $participant->snapshot_instrument_name,
                'class_type' => $participant->snapshot_class_type, 'grade' => $participant->snapshot_grade,
                'duration_min' => $participant->snapshot_duration_min, 'price_per_month' => $participant->snapshot_package_price,
            ],
        ];
    }

    private function recalculate(Invoice $invoice): void
    {
        $invoice->update(['total_amount' => $invoice->items()->sum('amount')]);
    }

    private function assertUnpaid(Invoice $invoice): void
    {
        $payments = $invoice->payments()->whereNull('voided_at')->lockForUpdate()->get();
        abort_if($payments->isNotEmpty() || $invoice->paid_amount > 0 || $invoice->status !== Invoice::STATUS_UNPAID,
            422, 'Void seluruh pembayaran invoice terlebih dahulu sebelum mengubah peserta.');
    }

    /** Retry hanya diterima bila seluruh tautan, snapshot item, dan total masih konsisten. */
    private function assertConsistent(Event $event, Collection $participants, Collection $invoices): void
    {
        $groups = $participants->groupBy('student_id');
        abort_unless($invoices->count() === $groups->count(), 422, 'Jumlah invoice event tidak konsisten.');
        foreach ($groups as $studentId => $group) {
            $studentInvoices = $invoices->where('student_id', $studentId);
            abort_unless($studentInvoices->count() === 1, 422, 'Murid harus memiliki tepat satu invoice aktif per event.');
            $invoice = $studentInvoices->first();
            $items = $invoice->items()->with('component:id,code')->lockForUpdate()->get();
            abort_unless($invoice->enrollment_id === null
                && $invoice->total_amount === (int) $items->sum('amount')
                && $items->where('item_code', 'MC')->count() === $group->count(), 422, 'Total atau jumlah item invoice event tidak konsisten.');
            abort_if($invoice->status === Invoice::STATUS_UNPAID
                && ! $invoice->due_date?->equalTo($event->payment_due_date), 422, 'Batas pembayaran invoice tidak sesuai dengan event.');
            foreach ($group as $participant) {
                $item = $items->firstWhere('id', $participant->invoice_item_id);
                abort_unless($participant->invoice_id === $invoice->id && $item && $item->item_code === 'MC'
                    && $item->component?->code === 'MC' && $participant->snapshot_mc_fee > 0
                    && $item->amount === $participant->snapshot_mc_fee
                    // MySQL dapat mengurutkan ulang key kolom JSON ketika dibaca.
                    && $item->metadata == $this->metadata($participant), 422, 'Tautan atau snapshot tagihan peserta tidak konsisten.');
            }
        }
    }

    private function audit(Invoice $invoice, User $actor, string $action, ?array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'user_id' => $actor->id, 'user_name' => $actor->name, 'action' => $action,
            'entity_type' => 'Invoice', 'entity_id' => $invoice->id, 'entity_label' => $invoice->invoice_number,
            'old_values' => $oldValues, 'new_values' => $newValues, 'ip_address' => request()->ip(),
        ]);
    }
}
