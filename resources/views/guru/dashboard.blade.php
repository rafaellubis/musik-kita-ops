<x-guru-layout title="Dashboard">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-on-background">Halo, {{ $teacher->name }}</h1>
    <p class="text-sm text-outline">{{ \Carbon\Carbon::today()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
</div>

{{-- ===== BANNER SESI PENDING (tampil hanya jika ada) ===== --}}
@if($jumlahPending > 0)
<div class="mx-4 mb-2">
    <a href="{{ route('guru.sesi-pending.index') }}"
       class="flex items-start gap-3 bg-white border border-secondary/20 border-l-4
              border-l-secondary rounded-2xl px-4 py-3 hover:bg-surface-container-low transition-colors shadow-sm">
        <span class="text-xl shrink-0">📋</span>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-secondary text-sm">{{ $jumlahPending }} Sesi Pending</div>
            <div class="text-xs text-outline mt-0.5">Murid izin, belum ada jadwal pengganti — tap untuk detail</div>
        </div>
        <span class="text-outline self-center text-lg">›</span>
    </a>
</div>
@endif

{{-- ===== SESI HARI INI ===== --}}
<div class="px-4 py-3">
    <h2 class="text-xs font-semibold tracking-widest text-outline uppercase mb-3">Sesi Hari Ini</h2>

    @forelse($sesiHariIni as $idx => $sesi)
        @include('guru._sesi-card', ['sesi' => $sesi, 'teacher' => $teacher, 'sIdx' => $idx])
    @empty
        <div class="bg-white rounded-2xl border border-outline-variant/30 px-4 py-10 text-center shadow-sm">
            <div class="text-3xl mb-2">🎵</div>
            <div class="text-outline text-sm">Tidak ada sesi hari ini.</div>
        </div>
    @endforelse
</div>

{{-- ===== RINGKASAN BULAN INI ===== --}}
<div class="px-4 pb-8">
    <h2 class="text-xs font-semibold tracking-widest text-outline uppercase mb-3">
        {{ \Carbon\Carbon::now()->locale('id')->isoFormat('MMMM Y') }}
    </h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-2xl border border-outline-variant/30 px-4 py-4 shadow-sm">
            <div class="text-2xl font-bold text-on-surface">{{ $totalSesiBulan }}</div>
            <div class="text-xs text-outline mt-0.5">Sesi Terlaksana</div>
        </div>
        <div class="bg-white rounded-2xl border border-outline-variant/30 px-4 py-4 shadow-sm">
            @if($slipBulanIni)
                <div class="text-lg font-bold text-on-surface leading-tight">
                    Rp {{ number_format($slipBulanIni->total_honor, 0, ',', '.') }}
                </div>
                <div class="text-xs text-outline mt-0.5">
                    Honor {{ $slipBulanIni->status === 'PAID' ? '✓ Dibayar' : 'Estimasi' }}
                </div>
            @else
                <div class="text-sm text-outline pt-1">—</div>
                <div class="text-xs text-outline mt-0.5">Honor (belum dihitung)</div>
            @endif
        </div>
        {{-- Kartu Sesi Pending — hanya tampil jika ada pending --}}
        @if($jumlahPending > 0)
        <a href="{{ route('guru.sesi-pending.index') }}"
           class="bg-white rounded-2xl border border-outline-variant/30 px-4 py-4 shadow-sm hover:bg-surface-container-low transition-colors">
            <div class="text-2xl font-bold text-error">{{ $jumlahPending }}</div>
            <div class="text-xs text-outline mt-0.5">Sesi Pending</div>
        </a>
        @endif
    </div>
</div>

</x-guru-layout>
