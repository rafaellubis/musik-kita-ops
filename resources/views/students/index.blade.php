<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Daftar Murid</h2>
                <div class="text-xs text-gray-500 mt-0.5">{{ $students->total() }} murid ditampilkan</div>
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

    <div class="py-6 px-4 lg:px-8 space-y-5">

        {{-- Flash messages --}}
        @if(session('success'))
        <div class="p-3 rounded-lg text-sm fade-in-up"
             style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="p-3 rounded-lg text-sm fade-in-up"
             style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
            {{ session('error') }}
        </div>
        @endif

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
                        class="mk-filter-btn px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        style="background:rgba(212,168,83,0.15);color:#D4A853">
                    Filter
                </button>
                <a href="{{ route('students.index') }}"
                   class="mk-reset-btn px-4 py-2 rounded-lg text-sm transition-colors"
                   style="background:rgba(255,255,255,0.05);color:#8B92A8">
                    Reset
                </a>
            </div>
        </form>

        {{-- ===== TABEL MURID ===== --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden fade-in-up" style="animation-delay:140ms">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-500">Kode</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-500">Murid</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-widest text-gray-500">L/P</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-widest text-gray-500">Umur</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-500">Paket</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-gray-500">Guru</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-widest text-gray-500">Jadwal</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-widest text-gray-500">Status</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-widest text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $s)
                        @php $cfg = $statusCfg[$s->status] ?? $statusCfg['Calon']; @endphp
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer"
                            onclick="window.location='{{ route('students.show', $s->id) }}'">
                            <td class="px-4 py-3 font-mono text-xs font-semibold" style="color:#D4A853">
                                {{ $s->student_code }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-semibold text-gray-800">{{ $s->full_name }}</div>
                                @if($s->nickname)
                                <div class="text-xs text-gray-500">"{{ $s->nickname }}"</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-500">{{ $s->gender }}</td>
                            <td class="px-4 py-3 text-center text-sm text-gray-700">{{ $s->age ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 max-w-[130px] truncate">
                                {{ $s->package?->code ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ $s->assignedTeacher?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $sch = $s->primaryEnrollment?->schedules->where('is_active', true)->first();
                                    $hariMap = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                                @endphp
                                @if($sch)
                                <div class="text-xs text-gray-700">{{ $hariMap[$sch->day_of_week] ?? '—' }}</div>
                                <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($sch->start_time)->format('H:i') }}</div>
                                @else
                                <span class="text-xs text-gray-400">—</span>
                                @endif
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
                                   class="inline-block px-3 py-1 rounded-lg text-xs font-semibold transition-colors"
                                   style="background:rgba(212,168,83,0.15);color:#D4A853">
                                    Detail →
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-sm text-gray-400">
                                Tidak ada murid yang sesuai filter.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $students->withQueryString()->links() }}
            </div>
        </div>

    </div>
</x-app-layout>
