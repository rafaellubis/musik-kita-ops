<x-guru-layout title="Jadwal Saya">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-mk-text">Jadwal Saya</h1>
    <p class="text-sm text-mk-muted">
        {{ \Carbon\Carbon::parse($mulai)->locale('id')->isoFormat('D MMM') }} –
        {{ \Carbon\Carbon::parse($akhir)->locale('id')->isoFormat('D MMM Y') }}
    </p>
</div>

{{-- ===== MOBILE: Kartu per hari ===== --}}
<div class="lg:hidden px-4 pb-24 space-y-4">
    @php $grouped = $sesi->groupBy('session_date'); @endphp

    @forelse($grouped as $tanggal => $sesiHari)
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs font-semibold tracking-wide text-mk-muted uppercase">
                    {{ \Carbon\Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, D MMM') }}
                </span>
                @if($tanggal === $today)
                    <span class="text-[10px] bg-mk-accent text-white px-2 py-0.5 rounded-full font-semibold">Hari ini</span>
                @endif
            </div>

            @foreach($sesiHari as $s)
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm mb-2 overflow-hidden">
                    <div class="flex items-start justify-between px-4 py-3">
                        <div>
                            <div class="font-medium text-mk-text text-sm">{{ $s->student->full_name }}</div>
                            <div class="text-xs text-mk-muted mt-0.5">
                                {{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}
                                @if($s->room) · {{ $s->room->name }} @endif
                            </div>
                            @if($s->substitute_teacher_id === auth()->user()->teacher?->id)
                                <div class="text-[10px] text-blue-500 mt-0.5">Anda sebagai pengganti</div>
                            @endif
                        </div>
                        @include('guru._badge-status', ['status' => $s->status])
                    </div>

                    @if($tanggal === $today && $s->status === 'SCHEDULED')
                        <div x-data="{ showLate: false }" class="px-4 pb-3 space-y-2">
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('guru.absensi.update', $s) }}" class="flex-1">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="HADIR">
                                    <button type="submit"
                                            class="w-full py-2.5 rounded-xl bg-green-500 hover:bg-green-600 text-white font-semibold text-sm transition-all active:scale-[0.98]">
                                        ✓ Hadir
                                    </button>
                                </form>
                                <button @click="showLate = !showLate"
                                        class="flex-1 py-2.5 rounded-xl border-2 border-yellow-400 text-yellow-600 font-semibold text-sm hover:bg-yellow-50 transition-all active:scale-[0.98]">
                                    ⏱ Terlambat
                                </button>
                            </div>
                            <div x-show="showLate" x-transition>
                                <form method="POST" action="{{ route('guru.absensi.update', $s) }}" class="flex gap-2">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="HADIR_TERLAMBAT">
                                    <input type="number" name="late_minutes" min="1" max="60" placeholder="Menit terlambat"
                                           class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-300">
                                    <button type="submit"
                                            class="px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-white rounded-xl font-semibold text-sm">
                                        Simpan
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-12 text-center">
            <div class="text-3xl mb-2">📅</div>
            <div class="text-mk-muted text-sm">Tidak ada sesi dalam periode ini.</div>
        </div>
    @endforelse
</div>

{{-- ===== DESKTOP: Tabel ===== --}}
<div class="hidden lg:block px-6 pb-6">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Tanggal</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Murid</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Waktu</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Ruang</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 w-32"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($sesi as $s)
                    <tr class="{{ $s->session_date === $today ? 'bg-green-50/30' : '' }}">
                        <td class="px-4 py-3 text-mk-text">
                            {{ \Carbon\Carbon::parse($s->session_date)->locale('id')->isoFormat('ddd, D MMM') }}
                            @if($s->session_date === $today)
                                <span class="ml-1 text-[10px] bg-mk-accent text-white px-1.5 py-0.5 rounded-full">Hari ini</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-mk-text">{{ $s->student->full_name }}</div>
                            @if($s->substitute_teacher_id === auth()->user()->teacher?->id)
                                <div class="text-[10px] text-blue-500">Pengganti</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-mk-muted">
                            {{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}
                        </td>
                        <td class="px-4 py-3 text-mk-muted">{{ $s->room?->name ?? '—' }}</td>
                        <td class="px-4 py-3">@include('guru._badge-status', ['status' => $s->status])</td>
                        <td class="px-4 py-3 text-right">
                            @if($s->session_date === $today && $s->status === 'SCHEDULED')
                                <div x-data="{ open: false }" class="relative inline-block">
                                    <button @click="open = !open"
                                            class="text-xs px-3 py-1.5 rounded-lg bg-mk-accent hover:bg-mk-accent/80 text-white font-medium transition-colors">
                                        Input Absensi
                                    </button>
                                    <div x-show="open" x-transition @click.outside="open = false"
                                         class="absolute right-0 top-9 z-10 w-56 bg-white border border-gray-100 rounded-xl shadow-lg p-3 space-y-2">
                                        <form method="POST" action="{{ route('guru.absensi.update', $s) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="HADIR">
                                            <button type="submit" class="w-full py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium">✓ Hadir</button>
                                        </form>
                                        <form method="POST" action="{{ route('guru.absensi.update', $s) }}" class="flex gap-1.5">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="HADIR_TERLAMBAT">
                                            <input type="number" name="late_minutes" min="1" max="60" placeholder="mnt"
                                                   class="w-16 border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-yellow-300">
                                            <button type="submit" class="flex-1 py-2 bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg text-sm font-medium">⏱ Terlambat</button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-mk-muted">Tidak ada sesi dalam periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</x-guru-layout>
