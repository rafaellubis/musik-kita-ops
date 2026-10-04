<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventHonorEntry;
use App\Models\EventParticipant;
use App\Models\EventTeacherAssignment;
use App\Models\HonorSlip;
use App\Models\Invoice;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EventHonorService
{
    public function __construct(private EventInvoiceService $invoiceService) {}

    /** Lifecycle memegang transaksi hingga status event dan audit berhasil disimpan. */
    public function post(Event $event, User $actor): Collection
    {
        abort_unless($actor->hasAnyRole(['Owner', 'Admin']), 403);
        [$event, $participants, $assignments, $entries, $slips, $lockedEntries] = $this->lockRows($event);
        abort_unless($event->isPublished() || $event->isCompleted(), 422, 'Honor hanya dapat diposting saat penyelesaian event.');
        abort_unless($event->type === Event::TYPE_MINI_CONCERT, 422, 'Penyelesaian hanya tersedia untuk Mini Concert.');
        $active = $participants->where('status', EventParticipant::STATUS_ACTIVE);
        abort_if($active->isEmpty(), 422, 'Event harus memiliki minimal satu peserta aktif.');
        $invoices = $event->invoices()->where('status', '!=', Invoice::STATUS_VOID)
            ->orderBy('id')->lockForUpdate()->get();
        $this->invoiceService->assertConsistent($event, $active, $invoices);

        foreach ($active as $participant) {
            $assignment = $assignments->get($participant->active_teacher_assignment_id);
            abort_unless($assignment && $assignment->event_id === $event->id
                && $assignment->attendance_status === EventTeacherAssignment::ATTENDANCE_HADIR,
                422, 'Seluruh guru pendamping aktif harus dikonfirmasi HADIR.');
            abort_unless(in_array($participant->attendance_status, [EventParticipant::ATTENDANCE_HADIR, EventParticipant::ATTENDANCE_TIDAK_HADIR], true),
                422, 'Selesaikan absensi seluruh peserta aktif.');
            abort_unless(filled($participant->snapshot_package_code) && $participant->snapshot_package_price > 0
                && $participant->snapshot_honor_amount === (int) round($participant->snapshot_package_price * 0.5 / 4),
                422, 'Snapshot honor peserta tidak valid.');

            $entry = $entries->firstWhere('event_participant_id', $participant->id);
            // Snapshot entry tidak ditulis ulang ketika master paket berubah atau event dibuka kembali.
            abort_if($entry && ($entry->event_id !== $event->id
                || $entry->snapshot_package_code !== $participant->snapshot_package_code
                || $entry->snapshot_package_price !== $participant->snapshot_package_price
                || $entry->amount !== $participant->snapshot_honor_amount), 422, 'Snapshot entry honor tidak konsisten.');
            if ($event->isCompleted()) {
                abort_unless($entry && $entry->status === EventHonorEntry::STATUS_POSTED
                    && $entry->teacher_id === $assignment->teacher_id
                    && $slips->get($entry->honor_slip_id)?->teacher_id === $assignment->teacher_id,
                    422, 'Posting honor event yang selesai tidak lengkap.');
            }
        }
        abort_if($entries->where('status', EventHonorEntry::STATUS_POSTED)->contains(
            fn ($entry) => ! $active->contains('id', $entry->event_participant_id)
        ), 422, 'Peserta nonaktif masih memiliki posting honor.');
        if ($event->isCompleted()) {
            return $entries->where('status', EventHonorEntry::STATUS_POSTED)->values();
        }

        $teacherIds = $assignments->whereIn('id', $active->pluck('active_teacher_assignment_id'))->pluck('teacher_id');
        $affected = $slips->filter(fn ($slip) => $teacherIds->contains($slip->teacher_id) || $entries->contains('honor_slip_id', $slip->id));
        abort_if($affected->contains('status', HonorSlip::STATUS_PAID), 422, 'Slip honor terkait sudah dibayar.');
        $posted = collect();
        foreach ($active as $participant) {
            $teacherId = $assignments->get($participant->active_teacher_assignment_id)->teacher_id;
            $slip = $affected->firstWhere('teacher_id', $teacherId);
            if (! $slip) {
                $slip = HonorSlip::create([
                    'slip_number' => app(HonorCalculationService::class)->generateSlipNumber($event->event_date->year, $event->event_date->month),
                    'teacher_id' => $teacherId, 'year' => $event->event_date->year, 'month' => $event->event_date->month,
                    'base_honor' => 0, 'event_honor' => 0, 'transport_honor' => 0, 'other_honor' => 0,
                    'total_honor' => 0, 'status' => HonorSlip::STATUS_DRAFT, 'created_by' => $actor->id,
                ]);
                $affected->put($slip->id, $slip);
            }
            $entry = $entries->firstWhere('event_participant_id', $participant->id) ?? new EventHonorEntry([
                'event_id' => $event->id, 'event_participant_id' => $participant->id,
                'snapshot_package_id' => $participant->snapshot_package_id,
                'snapshot_package_code' => $participant->snapshot_package_code,
                'snapshot_package_price' => $participant->snapshot_package_price,
                'honor_formula' => 'package_price * 0.5 / 4', 'amount' => $participant->snapshot_honor_amount,
            ]);
            $old = $entry->exists ? $entry->getAttributes() : null;
            $entry->fill([
                'teacher_id' => $teacherId, 'honor_slip_id' => $slip->id,
                'status' => EventHonorEntry::STATUS_POSTED, 'posted_at' => now(), 'posted_by' => $actor->id,
            ])->save();
            if (! $old) {
                $entry->setRelation('event', $event);
                $lockedEntries->push($entry);
            }
            $this->audit($entry, $actor, $old);
            $posted->push($entry);
        }
        foreach ($affected as $slip) {
            $this->recalculateSlip($slip, $lockedEntries);
        }

        return $posted;
    }

    public function reverse(Event $event, User $actor, string $reason): void
    {
        abort_unless($actor->hasRole('Owner'), 403);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']])->validate();
        [$event, , , $entries, $slips, $lockedEntries] = $this->lockRows($event);
        abort_unless($event->isCompleted(), 422, 'Hanya event COMPLETED yang dapat dibuka kembali.');
        $affected = $slips->whereIn('id', $entries->pluck('honor_slip_id'));
        abort_if($affected->contains('status', HonorSlip::STATUS_PAID), 422, 'Event tidak dapat dibuka kembali karena slip honor sudah dibayar.');
        foreach ($entries->where('status', EventHonorEntry::STATUS_POSTED) as $entry) {
            $old = $entry->getAttributes();
            $entry->update([
                'status' => EventHonorEntry::STATUS_REVERSED, 'reversed_at' => now(),
                'reversed_by' => $actor->id, 'reversal_reason' => $reason,
            ]);
            $this->audit($entry, $actor, $old);
        }
        foreach ($affected as $slip) {
            $this->recalculateSlip($slip, $lockedEntries);
        }
    }

    /** Hanya mengubah komponen event; honor sesi dan komponen manual tetap dipertahankan. */
    public function recalculateSlip(HonorSlip $slip, ?Collection $lockedEntries = null): HonorSlip
    {
        abort_if($slip->isLocked(), 422, 'Slip honor sudah dibayar dan tidak dapat dihitung ulang.');
        $entries = ($lockedEntries ?? EventHonorEntry::where('teacher_id', $slip->teacher_id)
            ->whereHas('event', fn ($query) => $query->whereYear('event_date', $slip->year)->whereMonth('event_date', $slip->month))
            ->with('event:id,name,event_date')->orderBy('id')->lockForUpdate()->get())
            ->where('teacher_id', $slip->teacher_id)
            ->where('status', EventHonorEntry::STATUS_POSTED)
            ->filter(fn ($entry) => $entry->event->event_date->year === $slip->year
                && $entry->event->event_date->month === $slip->month)
            ->sortBy([['event_id', 'asc'], ['id', 'asc']]);
        $slip->event_honor = $entries->sum('amount');
        $slip->event_honor_note = $entries->isEmpty() ? null : Str::limit($entries->pluck('event.name')->unique()->implode(' | '), 255, '');
        $slip->recalcTotal();
        $slip->save();

        return $slip;
    }

    private function lockRows(Event $event): array
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Posting/pembalikan honor harus berada dalam transaksi lifecycle event.');
        }
        $event = Event::query()->lockForUpdate()->findOrFail($event->id);
        $participants = $event->participants()->orderBy('id')->lockForUpdate()->get();
        $assignments = $event->teacherAssignments()->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        // Event sudah dikunci, sehingga referensi entry stabil saat guru dikumpulkan sebelum entry dikunci.
        $entryRefs = EventHonorEntry::where(fn ($query) => $query->where('event_id', $event->id)
            ->orWhereIn('event_participant_id', $participants->pluck('id')))->get(['teacher_id', 'honor_slip_id']);
        $teacherIds = $assignments
            ->whereIn('id', $participants->where('status', EventParticipant::STATUS_ACTIVE)
                ->pluck('active_teacher_assignment_id'))
            ->pluck('teacher_id')
            ->merge($entryRefs->pluck('teacher_id'))
            ->filter()->unique()->sort()->values();
        Teacher::whereIn('id', $teacherIds)->orderBy('id')->lockForUpdate()->get();

        // Locking read melihat commit terbaru setelah menunggu Teacher, tidak memakai snapshot ordinary read di atas.
        $lockedEntries = EventHonorEntry::where(function ($query) use ($event, $participants, $teacherIds) {
            $query->where('event_id', $event->id)
                ->orWhereIn('event_participant_id', $participants->pluck('id'))
                ->orWhere(function ($query) use ($event, $teacherIds) {
                    $query->whereIn('teacher_id', $teacherIds)
                        ->whereHas('event', fn ($eventQuery) => $eventQuery
                            ->whereYear('event_date', $event->event_date->year)
                            ->whereMonth('event_date', $event->event_date->month));
                });
        })->with('event:id,name,event_date')->orderBy('id')->lockForUpdate()->get();
        $entries = $lockedEntries->filter(fn ($entry) => $entry->event_id === $event->id
            || $participants->contains('id', $entry->event_participant_id))->values();
        // Entry semua guru/bulan sudah current dan terkunci sebelum slip dihitung ulang.
        $slips = HonorSlip::forMonth($event->event_date->year, $event->event_date->month)
            ->whereIn('teacher_id', $teacherIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        abort_if($entries->contains(fn ($entry) => $entry->event_id !== $event->id
            || ! $participants->contains('id', $entry->event_participant_id)
            || ($entry->honor_slip_id && (! $slips->has($entry->honor_slip_id)
                || $slips->get($entry->honor_slip_id)->teacher_id !== $entry->teacher_id))),
            422, 'Tautan entry honor atau periode slip tidak konsisten.');

        return [$event, $participants, $assignments, $entries, $slips, $lockedEntries];
    }

    private function audit(EventHonorEntry $entry, User $actor, ?array $old): void
    {
        AuditLog::create([
            'user_id' => $actor->id, 'user_name' => $actor->name,
            'action' => $old ? AuditLog::ACTION_UPDATE : AuditLog::ACTION_CREATE,
            'entity_type' => 'EventHonorEntry', 'entity_id' => $entry->id,
            'entity_label' => 'Event #'.$entry->event_id, 'old_values' => $old,
            'new_values' => $entry->getAttributes(), 'ip_address' => request()->ip(),
        ]);
    }
}
