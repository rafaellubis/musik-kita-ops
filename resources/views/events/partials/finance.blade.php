<div class="space-y-5">
    <div>
        <h2 class="text-lg font-semibold text-mk-text">Keuangan event</h2>
        <p class="mt-1 text-sm text-mk-muted">Ringkasan berbasis transaksi event. Nilai final akan mengikuti posting keuangan.</p>
    </div>

    <dl class="overflow-hidden rounded-lg border border-mk-borderLight bg-mk-card divide-y divide-mk-borderLight">
        @foreach([
            ['Target tagihan', 'target'],
            ['Tagihan terbit', 'billed'],
            ['Pendapatan diterima', 'collected'],
            ['Belum diterima', 'outstanding'],
        ] as [$label, $key])
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <dt class="text-sm font-semibold text-mk-muted">{{ $label }}</dt>
                <dd class="money-value font-mono text-base font-bold text-mk-text" data-money-mask="Rp •••••••">
                    Rp {{ number_format($financeSummary[$key], 0, ',', '.') }}
                </dd>
            </div>
        @endforeach
    </dl>

    <div class="rounded-lg border border-mk-borderLight bg-mk-card p-5">
        <h3 class="font-semibold text-mk-text">Honor dan margin kas</h3>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold text-mk-muted">Estimasi honor</dt>
                <dd class="money-value mt-1 font-mono text-sm font-semibold text-mk-text" data-money-mask="Rp •••••••">Rp {{ number_format($financeSummary['estimated_honor'], 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-mk-muted">Honor final</dt>
                <dd class="money-value mt-1 font-mono text-sm font-semibold text-mk-text" data-money-mask="Rp •••••••">Rp {{ number_format($financeSummary['final_honor'], 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-mk-muted">Margin kas</dt>
                <dd class="money-value mt-1 font-mono text-sm font-semibold text-mk-text" data-money-mask="Rp •••••••">Rp {{ number_format($financeSummary['cash_margin'], 0, ',', '.') }}</dd>
            </div>
        </dl>
    </div>

    <section class="overflow-hidden rounded-lg border border-mk-borderLight bg-mk-card">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-mk-borderLight bg-mk-surface px-4 py-3">
            <div>
                <h3 class="font-semibold text-mk-text">Invoice event</h3>
                <p class="mt-0.5 text-xs text-mk-muted">Riwayat VOID tetap ditampilkan, tetapi tidak masuk ringkasan.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <span class="rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-amber-900">Belum lunas {{ $financeSummary['invoice_counts']['UNPAID'] }}</span>
                <span class="rounded-full border border-blue-300 bg-blue-50 px-2.5 py-1 text-blue-900">Sebagian {{ $financeSummary['invoice_counts']['PARTIAL'] }}</span>
                <span class="rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-emerald-900">Lunas {{ $financeSummary['invoice_counts']['PAID'] }}</span>
            </div>
        </div>

        <div class="space-y-1.5 p-1.5">
            @forelse($event->invoices as $invoice)
                @php
                    $statusClass = match ($invoice->status) {
                        'PAID' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
                        'PARTIAL' => 'border-blue-300 bg-blue-50 text-blue-900',
                        'VOID' => 'border-red-300 bg-red-50 text-red-900',
                        default => 'border-amber-300 bg-amber-50 text-amber-900',
                    };
                @endphp
                <a href="{{ route('invoices.show', $invoice) }}" target="_blank" rel="noopener"
                   aria-label="Buka invoice {{ $invoice->invoice_number }} untuk {{ $invoice->student?->full_name ?? 'murid tidak tersedia' }} di tab baru"
                   class="mk-invoice-row flex min-h-16 cursor-pointer flex-wrap items-center justify-between gap-3 rounded-[10px] px-4 py-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent focus-visible:ring-offset-2">
                    <div>
                        <span class="font-mono text-sm font-bold text-mk-text">
                            {{ $invoice->invoice_number }}
                        </span>
                        <p class="mt-0.5 text-xs text-mk-muted">{{ $invoice->student?->full_name ?? 'Murid tidak tersedia' }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="money-value font-mono text-sm font-semibold text-mk-text" data-money-mask="Rp •••••••">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</span>
                        <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $invoice->status }}</span>
                    </div>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-mk-muted">Belum ada invoice event.</p>
            @endforelse
        </div>
    </section>
</div>
