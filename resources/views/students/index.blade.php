<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Daftar Murid</h2>
                <div class="text-xs text-mk-dim mt-0.5">{{ $students->total() }} murid ditampilkan</div>
            </div>
            @hasanyrole('Owner|Admin')
            <a href="{{ route('students.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary"
               >
                + Tambah Murid
            </a>
            @endhasanyrole
        </div>
    </x-slot>

    @php
    // Konfigurasi warna per status — pakai inline style agar tidak terpengaruh .dark-content override
    $statusCfg = [
        'Calon'              => ['bg' => 'rgba(139,146,168,0.12)', 'color' => '#8B92A8',  'dot' => '#8B92A8'],
        'Trial'              => ['bg' => 'rgba(167,139,250,0.12)', 'color' => '#A78BFA',  'dot' => '#A78BFA'],
        'Aktif'              => ['bg' => 'rgba(52,211,153,0.12)',  'color' => '#34D399',  'dot' => '#34D399'],
        'Cuti'               => ['bg' => 'rgba(251,191,36,0.12)', 'color' => '#FBBF24',  'dot' => '#FBBF24'],
        'Selesai'            => ['bg' => 'rgba(96,165,250,0.12)', 'color' => '#60A5FA',  'dot' => '#60A5FA'],
        'Mengundurkan Diri'  => ['bg' => 'rgba(248,113,113,0.12)','color' => '#F87171',  'dot' => '#F87171'],
    ];
    $activeStatus = request('status');
    @endphp

    <div class="py-10 px-4 lg:px-10 space-y-6">

        {{-- Flash messages --}}
        {{-- ===== STATUS FILTER CARDS ===== --}}
        <div class="flex flex-wrap gap-3 fade-in-up" style="animation-delay:0ms">
            @foreach($statusCfg as $st => $cfg)
            @php
                $isActive = $activeStatus === $st;
                $count    = $stats[$st] ?? 0;
                $href     = route('students.index', array_merge(request()->except(['status','page']), $isActive ? [] : ['status' => $st]));
            @endphp
            <a href="{{ $href }}"
               @class([
                   'rounded-xl px-4 py-2.5 transition-all duration-150 select-none cursor-pointer',
                   'mk-status-card-inactive' => !$isActive,
               ])
               style="{{ $isActive
                   ? 'background:' . $cfg['color'] . '22;border:1px solid ' . $cfg['dot'] . '60'
                   : '' }}">
                <div class="text-xl font-bold leading-none" style="color:{{ $cfg['color'] }}">{{ $count }}</div>
                <div class="text-xs mt-1 mk-status-label" style="color:{{ $isActive ? $cfg['color'] : '' }}">{{ $st }}</div>
            </a>
            @endforeach
        </div>

        {{-- ===== FILTER BAR ===== --}}
        <form method="GET" action="{{ route('students.index') }}" class="fade-in-up" style="animation-delay:80ms">
            <div class="flex flex-wrap gap-3 items-center">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="🔍  Cari nama atau kode murid..."
                       class="flex-1 min-w-[200px] text-sm rounded-lg px-3 py-2">
                <input type="hidden" name="status" value="{{ request('status') }}">
                <select name="instrument_id" class="text-sm rounded-lg px-3 py-2">
                    <option value="">Semua Instrumen</option>
                    @foreach($instruments as $inst)
                    <option value="{{ $inst->id }}" {{ request('instrument_id') == $inst->id ? 'selected' : '' }}>
                        {{ $inst->name }}
                    </option>
                    @endforeach
                </select>
                <select name="package_id" class="text-sm rounded-lg px-3 py-2">
                    <option value="">Semua Paket</option>
                    @foreach($packages as $pkg)
                    <option value="{{ $pkg->id }}" {{ request('package_id') == $pkg->id ? 'selected' : '' }}>
                        {{ $pkg->code }}
                    </option>
                    @endforeach
                </select>
                <button type="submit"
                        class="mk-filter-btn px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                    Filter
                </button>
                <a href="{{ route('students.index') }}"
                   class="mk-reset-btn px-4 py-2 rounded-lg text-sm transition-colors">
                    Reset
                </a>
            </div>
        </form>

        {{-- ===== TABEL MURID ===== --}}
        <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden fade-in-up" style="animation-delay:140ms">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-mk-borderLight bg-mk-surface">
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-mk-dim">Kode</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-mk-dim">Murid</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-widest text-mk-dim">L/P</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-widest text-mk-dim">Umur</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-mk-dim">Paket</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-mk-dim">Guru</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-widest text-mk-dim">Jadwal</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-widest text-mk-dim">Status</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-widest text-mk-dim">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $s)
                        @php
                            $cfg = $statusCfg[$s->status] ?? $statusCfg['Calon'];
                            $hariMap = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                        @endphp
                        <tr class="border-b border-mk-borderLight hover:bg-mk-surface transition-colors cursor-pointer"
                            onclick="window.location='{{ route('students.show', $s->id) }}'">
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-secondary">
                                {{ $s->student_code }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-semibold text-mk-text">{{ $s->full_name }}</div>
                                @if($s->nickname)
                                <div class="text-xs text-mk-dim">"{{ $s->nickname }}"</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-mk-dim">{{ $s->gender }}</td>
                            <td class="px-4 py-3 text-center text-sm text-mk-muted">{{ $s->age ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-mk-dim">
                                @forelse($s->activeEnrollments as $enr)
                                <div @class(['font-mono', 'mt-1' => !$loop->first])>{{ $enr->package?->code ?? '—' }}</div>
                                @empty
                                <span>—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-sm text-mk-muted">
                                @forelse($s->activeEnrollments as $enr)
                                <div @class(['mt-1' => !$loop->first])>{{ $enr->teacher?->name ?? '—' }}</div>
                                @empty
                                <span>—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-center">
                                @forelse($s->activeEnrollments as $enr)
                                @php $sch = $enr->schedules->first(); @endphp
                                <div @class(['mt-1' => !$loop->first])>
                                    @if($sch)
                                    <div class="text-xs text-mk-muted">{{ $hariMap[$sch->day_of_week] ?? '—' }}</div>
                                    <div class="text-xs text-mk-dim">{{ \Carbon\Carbon::parse($sch->start_time)->format('H:i') }}</div>
                                    @else
                                    <span class="text-xs text-mk-dim">—</span>
                                    @endif
                                </div>
                                @empty
                                <span class="text-xs text-mk-dim">—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                                      style="background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0"
                                          style="background:{{ $cfg['dot'] }}"></span>
                                    {{ $s->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                                <a href="{{ route('students.show', $s->id) }}"
                                   class="inline-block px-3 py-1 rounded-lg text-xs font-semibold transition-colors bg-secondary/15 text-secondary hover:bg-secondary/25">
                                    Detail →
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-sm text-mk-dim">
                                Tidak ada murid yang sesuai filter.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-4 py-3 border-t border-mk-borderLight">
                {{ $students->withQueryString()->links() }}
            </div>
        </div>

    </div>
</x-app-layout>
