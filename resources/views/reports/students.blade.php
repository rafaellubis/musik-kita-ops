<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Laporan Murid</h2>
                <div class="text-xs text-mk-dim mt-0.5">{{ $monthName }}</div>
            </div>
            <div class="flex items-center gap-3 no-print">
                <form method="GET" action="{{ route('reports.students') }}" class="flex items-center gap-2">
                    <select name="year" class="border-mk-border focus:border-mk-accent focus:ring-mk-accent rounded-lg text-xs py-1.5 px-3 text-mk-text bg-white">
                        @foreach(range(now()->year, now()->year - 2) as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                    <select name="month" class="border-mk-border focus:border-mk-accent focus:ring-mk-accent rounded-lg text-xs py-1.5 px-3 text-mk-text bg-white">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ Carbon\Carbon::create(null, $m, 1)->locale('id')->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="px-4 py-1.5 bg-mk-accentDim hover:bg-mk-accentDim/80 text-secondary rounded-lg text-xs font-bold transition-all">
                        Tampil
                    </button>
                </form>
                <a href="{{ route('students.index') }}"
                   class="px-4 py-1.5 border border-secondary text-secondary hover:bg-secondary/10 rounded-lg text-xs font-bold transition-all flex items-center gap-1">
                    Data Murid →
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 space-y-5">

        {{-- ===== STATISTIK BULAN INI ===== --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Card 1: Murid Baru --}}
            <div class="bg-mk-card rounded-2xl p-5 border border-mk-borderLight shadow-sm fade-in-up" style="animation-delay:0ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-mk-dim uppercase tracking-widest font-semibold mb-2">Murid Baru Aktif</div>
                        <div class="text-3xl font-bold text-secondary leading-none">{{ $muridBaru }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 bg-secondary/10">📈</div>
                </div>
            </div>

            {{-- Card 2: Mundur --}}
            <div class="bg-mk-card rounded-2xl p-5 border border-mk-borderLight shadow-sm fade-in-up" style="animation-delay:60ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-mk-dim uppercase tracking-widest font-semibold mb-2">Mundur / Selesai</div>
                        <div class="text-3xl font-bold text-error leading-none">{{ $muridMundur }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 bg-error-container/40">📉</div>
                </div>
            </div>

            {{-- Card 3: Murid Aktif --}}
            <div class="bg-mk-card rounded-2xl p-5 border border-mk-borderLight shadow-sm fade-in-up" style="animation-delay:120ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-mk-dim uppercase tracking-widest font-semibold mb-2">Murid Aktif</div>
                        <div class="text-3xl font-bold text-mk-text leading-none">{{ $byStatus['Aktif'] ?? 0 }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 bg-mk-accentDim/30">🎓</div>
                </div>
            </div>

            {{-- Card 4: Total Terdaftar --}}
            <div class="bg-mk-card rounded-2xl p-5 border border-mk-borderLight shadow-sm fade-in-up" style="animation-delay:180ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-mk-dim uppercase tracking-widest font-semibold mb-2">Total Terdaftar</div>
                        <div class="text-3xl font-bold text-mk-muted leading-none">{{ array_sum($byStatus) }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 bg-mk-surfaceHover">👥</div>
                </div>
            </div>
        </div>

        {{-- ===== DISTRIBUSI PER STATUS ===== --}}
        <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden fade-in-up" style="animation-delay:240ms">
            <div class="px-5 py-4 border-b border-mk-borderLight bg-mk-bg/30">
                <h3 class="text-sm font-semibold text-mk-text">Distribusi per Status</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        @php
                            $statusCfg = [
                                'Aktif'               => ['bg' => 'rgba(44,105,78,0.12)',   'color' => '#2C694E'],
                                'Trial'               => ['bg' => 'rgba(167,139,250,0.12)', 'color' => '#A78BFA'],
                                'Calon'               => ['bg' => 'rgba(139,146,168,0.12)', 'color' => '#8B92A8'],
                                'Cuti'                => ['bg' => 'rgba(196,122,69,0.12)',  'color' => '#C47A45'],
                                'Selesai'             => ['bg' => 'rgba(96,165,250,0.12)',  'color' => '#60A5FA'],
                                'Mengundurkan Diri'   => ['bg' => 'rgba(248,113,113,0.12)', 'color' => '#F87171'],
                            ];
                            $total = max(1, array_sum($byStatus));
                        @endphp
                        @foreach($statusCfg as $status => $cfg)
                            @php $count = $byStatus[$status] ?? 0; @endphp
                            @if($count > 0)
                            <tr class="border-b border-mk-borderLight hover:bg-mk-surface/40 transition-colors">
                                <td class="px-5 py-3 w-8/12">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold shrink-0 min-w-[120px] justify-center"
                                              style="background: {{ $cfg['bg'] }}; color: {{ $cfg['color'] }}">
                                            {{ $status }}
                                        </span>
                                        {{-- Progress bar --}}
                                        <div class="flex-1 bg-mk-surface rounded-full h-2 overflow-hidden border border-mk-borderLight">
                                            <div class="h-2 rounded-full transition-all duration-500"
                                                 style="width: {{ round($count / $total * 100) }}%; background-color: {{ $cfg['color'] }}"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-mk-text text-sm">
                                    {{ $count }}
                                </td>
                                <td class="px-5 py-3 text-right text-mk-dim text-xs font-mono">
                                    {{ round($count / $total * 100) }}%
                                </td>
                            </tr>
                            @endif
                        @endforeach
                        <tr class="bg-mk-surface/50 border-t border-mk-border">
                            <td class="px-5 py-3 font-semibold text-mk-muted text-sm">Total</td>
                            <td class="px-5 py-3 text-right font-bold text-mk-text text-sm">{{ array_sum($byStatus) }}</td>
                            <td class="px-5 py-3 text-right text-mk-dim text-xs font-mono">100%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ===== DISTRIBUSI PER INSTRUMEN ===== --}}
        @if($byInstrument->count() > 0)
        <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden fade-in-up" style="animation-delay:300ms">
            <div class="px-5 py-4 border-b border-mk-borderLight bg-mk-bg/30">
                <h3 class="text-sm font-semibold text-mk-text">Distribusi Murid Aktif per Instrumen</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-mk-borderLight bg-mk-surface">
                        <tr>
                            <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-widest text-mk-dim">Instrumen</th>
                            <th class="px-5 py-3 text-right text-[10px] font-semibold uppercase tracking-widest text-mk-dim">Murid Aktif</th>
                            <th class="px-5 py-3 text-right text-[10px] font-semibold uppercase tracking-widest text-mk-dim">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalAktif = $byInstrument->sum('total'); @endphp
                        @foreach($byInstrument as $row)
                        <tr class="border-b border-mk-borderLight hover:bg-mk-surface/40 transition-colors">
                            <td class="px-5 py-3 font-medium text-mk-text text-sm">{{ $row->instr_name }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-mk-text text-sm">{{ $row->total }}</td>
                            <td class="px-5 py-3 text-right text-mk-dim text-xs font-mono">
                                {{ $totalAktif > 0 ? round($row->total / $totalAktif * 100) : 0 }}%
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-mk-surface/50 border-t border-mk-border">
                        <tr>
                            <td class="px-5 py-3 font-semibold text-mk-muted text-sm">Total</td>
                            <td class="px-5 py-3 text-right font-bold text-mk-text text-sm">{{ $totalAktif }}</td>
                            <td class="px-5 py-3 text-right text-mk-dim text-xs font-mono">100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

    </div>
</x-app-layout>
