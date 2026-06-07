<x-guru-layout title="Dashboard">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-mk-text">Halo, {{ $teacher->name }}</h1>
    <p class="text-sm text-mk-muted">{{ \Carbon\Carbon::today()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
</div>

{{-- ===== BANNER SESI PENDING (tampil hanya jika ada) ===== --}}
@if($jumlahPending > 0)
<div class="mx-4 mb-2">
    <a href="{{ route('guru.sesi-pending.index') }}"
       class="flex items-start gap-3 bg-mk-card border border-mk-accent/20 border-l-4
              border-l-mk-accent rounded-xl px-4 py-3 hover:bg-mk-cardHover transition-colors">
        <span class="text-xl shrink-0">📋</span>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-mk-accent text-sm">{{ $jumlahPending }} Sesi Pending</div>
            <div class="text-xs text-mk-muted mt-0.5">Murid izin, belum ada jadwal pengganti — tap untuk detail</div>
        </div>
        <span class="text-mk-muted self-center text-lg">›</span>
    </a>
</div>
@endif

{{-- ===== SESI HARI INI ===== --}}
<div class="px-4 py-3">
    <h2 class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-3">Sesi Hari Ini</h2>

    @forelse($sesiHariIni as $sesi)
        <div class="bg-mk-card rounded-xl border border-mk-border shadow-sm mb-3 overflow-hidden">

            <div class="flex items-start justify-between px-4 py-3 border-b border-mk-borderLight">
                <div>
                    <div class="font-semibold text-mk-text">{{ $sesi->student->full_name }}</div>
                    @include('guru._sesi-identitas', ['sesi' => $sesi])
                    <div class="text-xs text-mk-muted mt-0.5">
                        {{ \Carbon\Carbon::parse($sesi->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($sesi->end_time)->format('H:i') }}
                        @if($sesi->room) · {{ $sesi->room->name }} @endif
                        @if($sesi->enrollment?->package) · {{ $sesi->enrollment->package->code }} @endif
                    </div>
                </div>
                @include('guru._badge-status', ['status' => $sesi->status])
            </div>

            @include('guru._sesi-absensi-actions', ['sesi' => $sesi, 'teacher' => $teacher])

        </div>
    @empty
        <div class="bg-mk-card rounded-xl border border-mk-border px-4 py-10 text-center">
            <div class="text-3xl mb-2">🎵</div>
            <div class="text-mk-muted text-sm">Tidak ada sesi hari ini.</div>
        </div>
    @endforelse
</div>

{{-- ===== RINGKASAN BULAN INI ===== --}}
<div class="px-4 pb-8">
    <h2 class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-3">
        {{ \Carbon\Carbon::now()->locale('id')->isoFormat('MMMM Y') }}
    </h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div class="bg-mk-card rounded-xl border border-mk-border px-4 py-4 shadow-sm">
            <div class="text-2xl font-bold text-mk-text">{{ $totalSesiBulan }}</div>
            <div class="text-xs text-mk-muted mt-0.5">Sesi Terlaksana</div>
        </div>
        <div class="bg-mk-card rounded-xl border border-mk-border px-4 py-4 shadow-sm">
            @if($slipBulanIni)
                <div class="text-lg font-bold text-mk-text leading-tight">
                    Rp {{ number_format($slipBulanIni->total_honor, 0, ',', '.') }}
                </div>
                <div class="text-xs text-mk-muted mt-0.5">
                    Honor {{ $slipBulanIni->status === 'PAID' ? '✓ Dibayar' : 'Estimasi' }}
                </div>
            @else
                <div class="text-sm text-mk-muted pt-1">—</div>
                <div class="text-xs text-mk-muted mt-0.5">Honor (belum dihitung)</div>
            @endif
        </div>
        {{-- Kartu Sesi Pending — hanya tampil jika ada pending --}}
        @if($jumlahPending > 0)
        <a href="{{ route('guru.sesi-pending.index') }}"
           class="bg-mk-card rounded-xl border border-mk-border px-4 py-4 shadow-sm hover:bg-mk-cardHover transition-colors">
            <div class="text-2xl font-bold text-red-500">{{ $jumlahPending }}</div>
            <div class="text-xs text-mk-muted mt-0.5">Sesi Pending</div>
        </a>
        @endif
    </div>
</div>

</x-guru-layout>
