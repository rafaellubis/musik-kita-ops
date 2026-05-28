<x-guru-layout title="Dashboard">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-mk-text">Halo, {{ $teacher->name }}</h1>
    <p class="text-sm text-mk-muted">{{ \Carbon\Carbon::today()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
</div>

{{-- ===== SESI HARI INI ===== --}}
<div class="px-4 py-3">
    <h2 class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-3">Sesi Hari Ini</h2>

    @forelse($sesiHariIni as $sesi)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm mb-3 overflow-hidden">

            <div class="flex items-start justify-between px-4 py-3 border-b border-gray-100">
                <div>
                    <div class="font-semibold text-mk-text">{{ $sesi->student->full_name }}</div>
                    <div class="text-xs text-mk-muted mt-0.5">
                        {{ \Carbon\Carbon::parse($sesi->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($sesi->end_time)->format('H:i') }}
                        @if($sesi->room) · {{ $sesi->room->name }} @endif
                        @if($sesi->enrollment?->package) · {{ $sesi->enrollment->package->code }} @endif
                    </div>
                </div>
                @include('guru._badge-status', ['status' => $sesi->status])
            </div>

            @if($sesi->status === 'SCHEDULED')
                <div x-data="{ showLate: false }" class="px-4 py-3 space-y-2">
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('guru.absensi.update', $sesi) }}" class="flex-1">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="HADIR">
                            <button type="submit"
                                    class="w-full py-2.5 rounded-xl font-semibold text-sm transition-colors appearance-none"
                                    style="background-color:#22c55e;color:#ffffff;">
                                ✓ Hadir
                            </button>
                        </form>
                        <button @click="showLate = !showLate"
                                class="flex-1 py-2.5 rounded-xl border-2 border-yellow-400 text-yellow-600
                                       font-semibold text-sm hover:bg-yellow-50 transition-colors appearance-none">
                            ⏱ Terlambat
                        </button>
                    </div>

                    <div x-show="showLate" x-transition class="pt-1">
                        <form method="POST" action="{{ route('guru.absensi.update', $sesi) }}" class="flex gap-2">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="HADIR_TERLAMBAT">
                            <div class="flex-1">
                                <input type="number" name="late_minutes" min="1" max="60"
                                       placeholder="Berapa menit terlambat?"
                                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                                              focus:outline-none focus:ring-2 focus:ring-yellow-300">
                            </div>
                            <button type="submit"
                                    class="px-5 py-2.5 rounded-xl font-semibold text-sm transition-colors appearance-none"
                                    style="background-color:#eab308;color:#ffffff;">
                                Simpan
                            </button>
                        </form>
                    </div>
                </div>
            @else
                {{-- Tampilkan status yang sudah tercatat agar guru tahu mengapa tombol tidak muncul --}}
                <div class="px-4 py-3 flex items-center gap-2">
                    @include('guru._badge-status', ['status' => $sesi->status])
                    <span class="text-xs text-mk-muted italic">
                        @if($sesi->status === 'LIBUR')
                            Sesi libur — tidak perlu absensi.
                        @elseif(in_array($sesi->status, ['HADIR', 'HADIR_TERLAMBAT']))
                            Absensi sudah tercatat.
                        @else
                            Status: {{ $sesi->status }}
                        @endif
                    </span>
                </div>
            @endif

        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-10 text-center">
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
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-4 shadow-sm">
            <div class="text-2xl font-bold text-mk-text">{{ $totalSesiBulan }}</div>
            <div class="text-xs text-mk-muted mt-0.5">Sesi Terlaksana</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-4 shadow-sm">
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
    </div>
</div>

</x-guru-layout>
