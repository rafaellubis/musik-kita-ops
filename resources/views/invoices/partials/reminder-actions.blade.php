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
    $reminderButtonSize = ($reminderCompact ?? false)
        ? 'rounded-full border px-2.5 py-0.5 text-xs'
        : 'rounded px-4 py-2 text-sm';
@endphp
<div class="{{ ($reminderCompact ?? false) ? 'flex flex-wrap items-center justify-center gap-2' : 'contents' }}">
    @if($canRemind)
        <button type="button" @click="show(@js($reminderConfig), 'history')"
                class="inline-flex items-center justify-center whitespace-nowrap border-mk-border font-semibold text-mk-muted ring-1 ring-inset ring-mk-border hover:bg-mk-cardHover focus-visible:ring-2 focus-visible:ring-mk-accent {{ $reminderButtonSize }}"
                :title="metadata[{{ $reminderInvoice->id }}]?.sent_at ? date(metadata[{{ $reminderInvoice->id }}].sent_at) : 'Lihat riwayat reminder'"
                x-text="metadata[{{ $reminderInvoice->id }}]?.label || 'Belum dikirim'">{{ $reminderInfo['label'] }}</button>
        @if(in_array($reminderInvoice->status, ['UNPAID', 'PARTIAL']) && $reminderInvoice->balance > 0)
            <button type="button" @click="show(@js($reminderConfig))"
                    class="inline-flex items-center justify-center whitespace-nowrap border-transparent font-bold btn-mk-primary focus-visible:ring-2 focus-visible:ring-mk-accent {{ $reminderButtonSize }}">Reminder WA</button>
        @endif
    @else
        <span class="text-xs text-mk-muted">{{ $reminderInfo['label'] }}</span>
    @endif
</div>
