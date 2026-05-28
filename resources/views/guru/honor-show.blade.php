<x-guru-layout :title="'Honor ' . \Carbon\Carbon::createFromDate($honorSlip->year, $honorSlip->month, 1)->locale('id')->isoFormat('MMMM Y')">

<div class="px-4 pt-4 pb-2">
    <a href="{{ route('guru.honor') }}" class="text-sm text-mk-muted hover:text-mk-text transition-colors">← Kembali</a>
</div>

{{-- ===== RINGKASAN SLIP ===== --}}
<div class="mx-4 mb-4 bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-5">
    <div class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-1">Slip Honor</div>
    <div class="text-xl font-bold text-mk-text">
        {{ \Carbon\Carbon::createFromDate($honorSlip->year, $honorSlip->month, 1)->locale('id')->isoFormat('MMMM Y') }}
    </div>
    <div class="text-xs text-mk-muted mt-0.5 mb-4">{{ $honorSlip->slip_number }}</div>

    <div class="space-y-2 border-t border-gray-100 pt-4">
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
        <div class="flex justify-between font-bold text-base border-t border-gray-100 pt-3 mt-1">
            <span>Total</span>
            <span>Rp {{ number_format($honorSlip->total_honor, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="mt-4">
        @if($honorSlip->status === 'PAID')
            <span class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded-full font-medium">✓ Sudah Dibayar</span>
        @else
            <span class="text-xs bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full font-medium">Menunggu Pembayaran</span>
        @endif
    </div>
</div>

{{-- ===== RINCIAN SESI ===== --}}
<div class="mx-4 pb-24 lg:pb-6">
    <h2 class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-3">Rincian Sesi</h2>

    {{-- Mobile: kartu --}}
    <div class="lg:hidden space-y-2">
        @forelse($sesi as $s)
            <div class="bg-white rounded-xl border border-gray-100 px-4 py-3">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-medium text-mk-text text-sm">{{ $s->student->full_name }}</div>
                        <div class="text-xs text-mk-muted mt-0.5">
                            {{ \Carbon\Carbon::parse($s->session_date)->locale('id')->isoFormat('D MMM') }}
                            · {{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}
                            @if($s->room) · {{ $s->room->name }} @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-semibold text-mk-text">Rp {{ number_format($s->honor_amount, 0, ',', '.') }}</div>
                        <div class="text-[10px] text-mk-muted mt-0.5">{{ $s->honor_code }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-100 px-4 py-8 text-center text-mk-muted text-sm">
                Tidak ada rincian sesi.
            </div>
        @endforelse
    </div>

    {{-- Desktop: tabel --}}
    <div class="hidden lg:block bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Tanggal</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Murid</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Ruang</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Kode</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Honor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($sesi as $s)
                    <tr>
                        <td class="px-4 py-3 text-mk-muted">{{ \Carbon\Carbon::parse($s->session_date)->locale('id')->isoFormat('D MMM Y') }}</td>
                        <td class="px-4 py-3 font-medium text-mk-text">{{ $s->student->full_name }}</td>
                        <td class="px-4 py-3 text-mk-muted">{{ $s->room?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-mk-muted">{{ $s->honor_code }}</td>
                        <td class="px-4 py-3 text-right font-medium text-mk-text">Rp {{ number_format($s->honor_amount, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-mk-muted">Tidak ada rincian sesi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</x-guru-layout>
