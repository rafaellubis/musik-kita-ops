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
    $reminderSent = ($reminderCompact ?? false) && $reminderInfo['status'] === 'SUCCESS';
    $reminderLabel = $reminderSent ? 'Terkirim' : $reminderInfo['label'];
@endphp
<div class="{{ ($reminderCompact ?? false) ? 'flex flex-wrap items-center justify-center gap-2' : 'contents' }}">
    @if($canRemind)
        <button type="button" @click="show(@js($reminderConfig), 'history')"
                class="inline-flex items-center justify-center whitespace-nowrap font-semibold ring-1 ring-inset focus-visible:ring-2 focus-visible:ring-mk-accent {{ $reminderButtonSize }}"
                :class="@js($reminderCompact ?? false) && metadata[{{ $reminderInvoice->id }}]?.status === 'SUCCESS' ? 'bg-emerald-50 border-emerald-200 text-emerald-800 ring-emerald-200 hover:bg-emerald-100' : 'border-mk-border text-mk-muted ring-mk-border hover:bg-mk-cardHover'"
                :title="metadata[{{ $reminderInvoice->id }}]?.sent_at ? date(metadata[{{ $reminderInvoice->id }}].sent_at) : 'Lihat riwayat reminder'"
                x-text="@js($reminderCompact ?? false) && metadata[{{ $reminderInvoice->id }}]?.status === 'SUCCESS' ? 'Terkirim' : (metadata[{{ $reminderInvoice->id }}]?.label || 'Belum dikirim')">{{ $reminderLabel }}</button>
        @if(in_array($reminderInvoice->status, ['UNPAID', 'PARTIAL']) && $reminderInvoice->balance > 0)
            <button type="button" @click="show(@js($reminderConfig))"
                    class="inline-flex items-center justify-center whitespace-nowrap border-transparent font-bold btn-mk-primary focus-visible:ring-2 focus-visible:ring-mk-accent {{ $reminderButtonSize }}">Reminder WA</button>
        @endif
    @else
        <span class="{{ $reminderSent ? 'inline-flex items-center whitespace-nowrap rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800' : 'text-xs text-mk-muted' }}">{{ $reminderLabel }}</span>
    @endif
</div>
