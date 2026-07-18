<x-guru-layout :title="'Honor ' . \Carbon\Carbon::createFromDate($honorSlip->year, $honorSlip->month, 1)->locale('id')->isoFormat('MMMM Y')">

<div class="px-4 pt-4 pb-2">
    <a href="{{ route('guru.honor') }}" class="text-sm text-mk-muted hover:text-mk-text transition-colors">← Kembali</a>
</div>

{{-- ===== RINGKASAN SLIP ===== --}}
<div class="mx-4 mb-4 bg-mk-card rounded-xl border border-mk-borderLight shadow-sm px-5 py-5">
    <div class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-1">Slip Honor</div>
    <div class="text-xl font-bold text-mk-text">
        {{ \Carbon\Carbon::createFromDate($honorSlip->year, $honorSlip->month, 1)->locale('id')->isoFormat('MMMM Y') }}
    </div>
    <h1 class="text-xs text-mk-dim mt-0.5 mb-4">{{ $honorSlip->slip_number }}</h1>

    <div class="space-y-2 border-t border-mk-borderLight pt-4">
        <div class="flex justify-between text-sm">
            <span class="text-mk-muted">Honor Pokok</span>
            <span class="font-medium text-mk-text">Rp {{ number_format($honorSlip->base_honor, 0, ',', '.') }}</span>
        </div>
        @if($honorSlip->event_honor > 0)
            <div class="flex justify-between text-sm">
                <span class="text-mk-muted">Honor Event</span>
                <span class="font-medium text-mk-text">Rp {{ number_format($honorSlip->event_honor, 0, ',', '.') }}</span>
            </div>
        @endif
        @if($honorSlip->transport_honor > 0)
            <div class="flex justify-between text-sm">
                <span class="text-mk-muted">Transport</span>
                <span class="font-medium text-mk-text">Rp {{ number_format($honorSlip->transport_honor, 0, ',', '.') }}</span>
            </div>
        @endif
        @if($honorSlip->other_honor > 0)
            <div class="flex justify-between text-sm">
                <span class="text-mk-muted">Lain-lain</span>
                <span class="font-medium text-mk-text">Rp {{ number_format($honorSlip->other_honor, 0, ',', '.') }}</span>
            </div>
        @endif
        <div class="flex justify-between font-bold text-base border-t border-mk-borderLight pt-3 mt-1 text-mk-text">
            <span>Total</span>
            <span>Rp {{ number_format($honorSlip->total_honor, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="mt-4">
        @if($honorSlip->status === 'PAID')
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-300">
                ✓ Sudah Dibayar
            </span>
        @else
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-300">
                Menunggu Pembayaran
            </span>
        @endif
    </div>
</div>

{{-- ===== RINCIAN SESI ===== --}}
<div class="mx-4 pb-24 lg:pb-6">
    <h2 class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-3">Rincian Sesi</h2>

    {{-- Mobile: kartu --}}
    <div class="lg:hidden space-y-2">
        @forelse($sesi as $s)
            <div class="bg-mk-card rounded-xl border border-mk-borderLight px-4 py-3 shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-medium text-mk-text text-sm">{{ $s->student->full_name }}</div>
                        <div class="text-xs text-mk-dim mt-0.5">
                            {{ \Carbon\Carbon::parse($s->session_date)->locale('id')->isoFormat('D MMM Y') }}
                            · <span class="font-semibold text-mk-muted">{{ $s->enrollment?->package?->code ?? '—' }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold text-mk-text">Rp {{ number_format($s->honor_amount, 0, ',', '.') }}</div>
                        <div class="text-[10px] font-mono text-mk-muted mt-0.5">{{ $s->honor_code }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-mk-card rounded-xl border border-mk-borderLight px-4 py-8 text-center text-mk-muted text-sm shadow-sm">
                Tidak ada rincian sesi.
            </div>
        @endforelse
    </div>

    {{-- Desktop: tabel --}}
    <div class="hidden lg:block bg-mk-card rounded-xl border border-mk-borderLight shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-mk-surface border-b border-mk-borderLight">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-bold text-mk-muted uppercase tracking-wider">Tanggal</th>
                    <th class="text-left px-4 py-3 text-xs font-bold text-mk-muted uppercase tracking-wider">Murid</th>
                    <th class="text-left px-4 py-3 text-xs font-bold text-mk-muted uppercase tracking-wider">Paket</th>
                    <th class="text-left px-4 py-3 text-xs font-bold text-mk-muted uppercase tracking-wider">Sesi</th>
                    <th class="text-left px-4 py-3 text-xs font-bold text-mk-muted uppercase tracking-wider">Kode</th>
                    <th class="text-right px-4 py-3 text-xs font-bold text-mk-muted uppercase tracking-wider">Honor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-mk-borderLight">
                @forelse($sesi as $s)
                    <tr class="hover:bg-mk-surface/30 transition-colors">
                        <td class="px-4 py-3 text-mk-dim">{{ \Carbon\Carbon::parse($s->session_date)->locale('id')->isoFormat('D MMM Y') }}</td>
                        <td class="px-4 py-3 font-semibold text-mk-text">{{ $s->student->full_name }}</td>
                        <td class="px-4 py-3 text-mk-text">{{ $s->enrollment?->package?->code ?? '—' }}</td>
                        <td class="px-4 py-3 text-mk-muted">Sesi ke-{{ $s->session_sequence ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-mk-muted">{{ $s->honor_code }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-mk-text">Rp {{ number_format($s->honor_amount, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-mk-muted">Tidak ada rincian sesi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</x-guru-layout>
