@php
    $reminderConfig = [
        'id' => $reminderInvoice->id, 'name' => $reminderInvoice->student?->full_name ?? 'Murid tidak tersedia',
        'number' => $reminderInvoice->invoice_number,
        'preview_url' => route('invoices.reminder.preview', $reminderInvoice),
        'history_url' => route('invoices.reminder.history', $reminderInvoice),
        'send_url' => route('invoices.reminder.send', $reminderInvoice),
    ];
    $reminderInfo = $reminderMetadata[$reminderInvoice->id];
    $canRemind = auth()->user()?->hasAnyRole(['Owner', 'Admin']);
@endphp
<div class="flex flex-wrap items-center gap-2">
    @if($canRemind)
        <button type="button" @click="show(@js($reminderConfig), 'history')"
                class="min-h-11 rounded-lg border border-mk-border px-3 py-2 text-xs font-semibold text-mk-muted hover:bg-mk-cardHover focus-visible:ring-2 focus-visible:ring-mk-accent"
                :title="metadata[{{ $reminderInvoice->id }}]?.sent_at ? date(metadata[{{ $reminderInvoice->id }}].sent_at) : 'Lihat riwayat reminder'"
                x-text="metadata[{{ $reminderInvoice->id }}]?.label || 'Belum dikirim'">{{ $reminderInfo['label'] }}</button>
        @if(in_array($reminderInvoice->status, ['UNPAID', 'PARTIAL']) && $reminderInvoice->balance > 0)
            <button type="button" @click="show(@js($reminderConfig))"
                    class="min-h-11 rounded-lg px-3 py-2 text-xs font-bold btn-mk-primary focus-visible:ring-2 focus-visible:ring-mk-accent">Reminder WA</button>
        @endif
    @else
        <span class="text-xs text-mk-muted">{{ $reminderInfo['label'] }}</span>
    @endif
</div>
