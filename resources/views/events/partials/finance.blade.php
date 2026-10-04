@php
    $finance = array_replace([
        'target' => 0,
        'billed' => 0,
        'collected' => 0,
        'outstanding' => 0,
        'estimated_honor' => 0,
        'final_honor' => 0,
        'cash_margin' => 0,
        'invoice_counts' => [],
    ], $financeSummary ?? []);
@endphp

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
                    Rp {{ number_format($finance[$key], 0, ',', '.') }}
                </dd>
            </div>
        @endforeach
    </dl>

    <div class="rounded-lg border border-mk-borderLight bg-mk-card p-5">
        <h3 class="font-semibold text-mk-text">Honor dan margin kas</h3>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold text-mk-muted">Estimasi honor</dt>
                <dd class="money-value mt-1 font-mono text-sm font-semibold text-mk-text" data-money-mask="Rp •••••••">Rp {{ number_format($finance['estimated_honor'], 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-mk-muted">Honor final</dt>
                <dd class="money-value mt-1 font-mono text-sm font-semibold text-mk-text" data-money-mask="Rp •••••••">Rp {{ number_format($finance['final_honor'], 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-mk-muted">Margin kas</dt>
                <dd class="money-value mt-1 font-mono text-sm font-semibold text-mk-text" data-money-mask="Rp •••••••">Rp {{ number_format($finance['cash_margin'], 0, ',', '.') }}</dd>
            </div>
        </dl>
    </div>
</div>
