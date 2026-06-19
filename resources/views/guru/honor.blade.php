<x-guru-layout title="Slip Honor">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-mk-text">Slip Honor</h1>
    <p class="text-sm text-mk-muted">Honor yang sudah dihitung oleh studio</p>
</div>

<div class="px-4 pb-24 lg:pb-6 space-y-3">
    @forelse($slips as $slip)
        <a href="{{ route('guru.honor.show', $slip) }}"
           class="block bg-mk-card rounded-xl border border-mk-borderLight shadow-sm hover:bg-mk-cardHover hover:border-mk-accent/40 active:scale-[0.99] transition-all overflow-hidden">
            <div class="flex items-center justify-between px-4 py-4">
                <div>
                    <div class="font-semibold text-mk-text">
                        {{ \Carbon\Carbon::createFromDate($slip->year, $slip->month, 1)->locale('id')->isoFormat('MMMM Y') }}
                    </div>
                    <div class="text-xs text-mk-dim mt-0.5">{{ $slip->slip_number }}</div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-mk-text text-base">Rp {{ number_format($slip->total_honor, 0, ',', '.') }}</div>
                    <div class="mt-1">
                        @if($slip->status === 'PAID')
                            <span class="inline-flex items-center text-[10px] bg-green-100 text-green-700 border border-green-300 px-2 py-0.5 rounded-full font-semibold">✓ Dibayar</span>
                        @else
                            <span class="inline-flex items-center text-[10px] bg-blue-100 text-blue-700 border border-blue-300 px-2 py-0.5 rounded-full font-semibold font-medium">Terhitung</span>
                        @endif
                    </div>
                </div>
            </div>
        </a>
    @empty
        <div class="bg-mk-card rounded-xl border border-mk-borderLight px-4 py-12 text-center shadow-sm">
            <div class="text-3xl mb-2">💰</div>
            <div class="text-mk-text text-sm font-semibold">Belum ada slip honor yang tersedia.</div>
            <div class="text-xs text-mk-muted mt-1">Slip honor muncul setelah dihitung oleh studio.</div>
        </div>
    @endforelse
</div>

</x-guru-layout>
