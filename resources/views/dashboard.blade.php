<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-xs text-mk-dim mb-0.5 font-medium">Selamat datang, {{ auth()->user()->name }}</div>
                <h2 class="font-semibold text-xl text-mk-text">Dashboard — {{ $monthName }}</h2>
            </div>
            <div class="flex gap-2">
                @if($isOwner)
                <a href="{{ route('reports.finance', ['year' => $year, 'month' => $month]) }}"
                   class="px-4 py-2 bg-mk-sidebar hover:bg-opacity-90 text-white rounded-lg text-xs font-semibold shadow-sm transition-all flex items-center gap-1">
                    Laporan Keuangan →
                </a>
                @endif
                <a href="{{ route('reports.students', ['year' => $year, 'month' => $month]) }}"
                   class="px-4 py-2 border border-secondary text-secondary hover:bg-secondary/10 rounded-lg text-xs font-semibold transition-all flex items-center gap-1">
                    Laporan Murid →
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 space-y-5">

        @if(!$isAdmin)
        {{-- ===== BARIS 1: KARTU KPI ===== --}}
        <div class="grid grid-cols-2 {{ $isOwner ? 'lg:grid-cols-4' : 'lg:grid-cols-2' }} gap-4">

            @if($isOwner)
            <div class="bg-mk-card rounded-2xl p-5 border border-mk-border shadow-sm fade-in-up" style="animation-delay:0ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-mk-dim uppercase tracking-widest font-semibold mb-2">Pendapatan Bulan Ini</div>
                        <div class="text-2xl font-bold text-secondary leading-none">
                            Rp {{ number_format($revenueBulan, 0, ',', '.') }}
                        </div>
                        <div class="mt-1.5 text-xs text-mk-dim font-medium">
                            Cash {{ number_format($revenueCash/1000000, 1, ',', '.') }}jt
                            · Transfer {{ number_format($revenueTransfer/1000000, 1, ',', '.') }}jt
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 bg-secondary/10"
                         title="Pendapatan">💰</div>
                </div>
            </div>

            <div class="bg-mk-card rounded-2xl p-5 border border-mk-borderLight shadow-sm fade-in-up" style="animation-delay:60ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-mk-dim uppercase tracking-widest font-semibold mb-2">Pengeluaran Bulan Ini</div>
                        <div class="text-2xl font-bold text-error leading-none">
                            Rp {{ number_format($pengeluaranBulan, 0, ',', '.') }}
                        </div>
                        <div class="mt-1.5 text-xs text-mk-dim font-medium">
                            Operasional transfer + isi petty cash
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 bg-error-container/40"
                         title="Pengeluaran">📉</div>
                </div>
            </div>

            <div class="bg-mk-card rounded-2xl p-5 border border-mk-borderLight shadow-sm fade-in-up" style="animation-delay:120ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-mk-dim uppercase tracking-widest font-semibold mb-2">Laba / Rugi</div>
                        <div class="text-2xl font-bold leading-none {{ $labaBulan >= 0 ? 'text-secondary' : 'text-error' }}">
                            {{ $labaBulan < 0 ? '-' : '' }}Rp {{ number_format(abs($labaBulan), 0, ',', '.') }}
                        </div>
                        <div class="mt-1.5 text-xs font-semibold {{ $labaBulan >= 0 ? 'text-secondary/90' : 'text-error/90' }}">
                            {{ $labaBulan >= 0 ? '↑ Surplus' : '↓ Defisit' }} bulan ini
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 {{ $labaBulan >= 0 ? 'bg-secondary/10' : 'bg-error-container/40' }}">
                        {{ $labaBulan >= 0 ? '📈' : '📉' }}
                    </div>
                </div>
            </div>
            @endif

            {{-- Saldo Petty Cash (Owner & Auditor) --}}
            <div class="bg-mk-card rounded-2xl p-5 border border-mk-borderLight shadow-sm fade-in-up"
                 style="animation-delay:{{ $isOwner ? '180ms' : '0ms' }}">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-mk-dim uppercase tracking-widest font-semibold mb-2">Saldo Petty Cash</div>
                        <div class="text-2xl font-bold leading-none {{ $saldoPettyCash >= 0 ? 'text-mk-text' : 'text-error' }}">
                            Rp {{ number_format(abs($saldoPettyCash), 0, ',', '.') }}
                        </div>
                        <div class="mt-1.5 text-xs text-mk-dim font-medium">
                            Float kas kecil studio
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 bg-mk-accentDim/30">💵</div>
                </div>
            </div>

            {{-- Murid Aktif untuk non-Owner agar row 1 tetap 2 kartu --}}
            @if(!$isOwner)
            <div class="bg-mk-card rounded-2xl p-5 border border-mk-borderLight shadow-sm fade-in-up" style="animation-delay:60ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-mk-dim uppercase tracking-widest font-semibold mb-2">Murid Aktif</div>
                        <div class="text-2xl font-bold text-mk-text leading-none">{{ $muridAktif }}</div>
                        <div class="mt-1.5 text-xs text-mk-dim font-medium">
                            Trial {{ $muridTrial }} · Cuti {{ $muridCuti }} · Calon {{ $muridCalon }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0 bg-secondary/10">🎓</div>
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- ===== BARIS 2: CHART P&L + DONUT INSTRUMEN (Owner only) ===== --}}
        @if($isOwner)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 fade-in-up" style="animation-delay:200ms">

            {{-- Area Chart: P&L 6 Bulan --}}
            <div class="lg:col-span-2 bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-mk-borderLight flex justify-between items-center bg-mk-bg/30">
                    <div>
                        <h3 class="text-sm font-semibold text-mk-text">Laporan Keuangan</h3>
                        <p class="text-xs text-mk-dim mt-0.5 font-medium">6 bulan terakhir</p>
                    </div>
                    <div class="flex items-center gap-3">
                        @foreach([['#5DB890','Pemasukan'],['#C47A45','Honor'],['#BA1A1A','Pengeluaran']] as [$clr,$lbl])
                        <div class="flex items-center gap-1.5 text-[11px] text-mk-muted font-semibold">
                            <span class="w-2.5 h-2.5 rounded-sm inline-block shrink-0 shadow-sm" style="background:{{ $clr }}"></span>{{ $lbl }}
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="p-5 flex-1">
                    <div id="chart-revenue"></div>
                </div>
            </div>

            {{-- Donut: Distribusi Instrumen --}}
            <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-mk-borderLight bg-mk-bg/30">
                    <h3 class="text-sm font-semibold text-mk-text">Distribusi Instrumen</h3>
                    <p class="text-xs text-mk-dim mt-0.5 font-medium">{{ $muridAktif }} murid aktif</p>
                </div>
                <div class="p-5 flex-1 flex flex-col justify-between">
                    <div id="chart-instrumen" class="my-auto"></div>
                    @php $instrumenColors = ['#D4A853','#60A5FA','#A78BFA','#34D399','#FBBF24','#F87171','#FB7185','#2DD4BF']; @endphp
                    <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 mt-2 pt-2 border-t border-mk-borderLight">
                        @foreach($instrumenChart as $idx => $ins)
                        <div class="flex items-center gap-1.5 text-xs min-w-0">
                            <span class="w-2.5 h-2.5 rounded-sm shrink-0 inline-block"
                                  style="background:{{ $instrumenColors[$idx % count($instrumenColors)] }}"></span>
                            <span class="text-mk-dim truncate font-medium">{{ $ins->name }}</span>
                            <span class="text-mk-text font-bold ml-auto shrink-0">{{ $ins->total }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ===== BARIS 3: BAR CHART ABSENSI + AGING PIUTANG ===== --}}
        <div class="grid grid-cols-1 {{ $isAdmin ? '' : 'lg:grid-cols-2' }} gap-5">

            {{-- Bar Chart: Absensi Mingguan --}}
            <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden fade-in-up" style="animation-delay:240ms">
                <div class="px-5 py-4 border-b border-mk-borderLight flex justify-between items-center bg-mk-bg/30">
                    <div>
                        <h3 class="text-sm font-semibold text-mk-text">Absensi Bulan Ini</h3>
                        <p class="text-xs text-mk-dim mt-0.5 font-medium">Per minggu</p>
                    </div>
                    <div class="flex items-center gap-3">
                        @foreach([['#5DB890','Hadir'],['#C47A45','Izin'],['#BA1A1A','Hangus']] as [$clr,$lbl])
                        <div class="flex items-center gap-1.5 text-[11px] text-mk-muted font-semibold">
                            <span class="w-2.5 h-2.5 rounded-sm inline-block shrink-0 shadow-sm" style="background:{{ $clr }}"></span>{{ $lbl }}
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="p-5">
                    <div id="chart-attendance"></div>
                </div>
            </div>

            @if(!$isAdmin)
            {{-- Aging Piutang --}}
            <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden fade-in-up" style="animation-delay:280ms">
                <div class="px-5 py-4 border-b border-mk-borderLight flex justify-between items-center bg-mk-bg/30">
                    <h3 class="text-sm font-semibold text-mk-text">Aging Piutang</h3>
                    <a href="{{ route('invoices.index') }}" class="text-xs text-secondary hover:text-secondary-container hover:underline transition-colors font-medium">Lihat tagihan →</a>
                </div>
                <div class="p-5 space-y-3.5">
                    @foreach([
                        ['Belum jatuh tempo', $aging['current'],   $agingCount['current'],   'text-mk-text font-medium',      'text-mk-text font-semibold'],
                        ['Telat 1–30 hari',   $aging['late1_30'], $agingCount['late1_30'], 'text-[#c47a45] font-medium',    'text-[#c47a45] font-bold'],
                        ['Telat > 30 hari',   $aging['late31'],   $agingCount['late31'],   'text-error font-medium',        'text-error font-extrabold'],
                    ] as [$lbl, $amt, $cnt, $lblCls, $amtCls])
                    <div class="flex items-center justify-between">
                        <span class="text-sm {{ $lblCls }}">{{ $lbl }}</span>
                        <div class="text-right">
                            <div class="text-sm {{ $amtCls }}">Rp {{ number_format($amt, 0, ',', '.') }}</div>
                            <div class="text-[10px] text-mk-dim font-medium">{{ $cnt }} tagihan</div>
                        </div>
                    </div>
                    @endforeach
                    <div class="flex justify-between items-center border-t border-mk-borderLight pt-3.5 mt-2">
                        <span class="text-sm font-semibold text-mk-muted">Total Piutang</span>
                        <span class="text-base font-bold {{ $totalPiutang > 0 ? 'text-error' : 'text-mk-muted' }}">
                            Rp {{ number_format($totalPiutang, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- ===== BARIS 4: STATISTIK MURID + INVOICE TERLAMA ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            @if($isAdmin)
            {{-- Daftar Absensi Hari Ini (Admin only) --}}
            <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden fade-in-up" style="animation-delay:320ms">
                <div class="px-5 py-4 border-b border-mk-borderLight flex justify-between items-center bg-mk-bg/30">
                    <h3 class="text-sm font-semibold text-mk-text">Daftar Absensi Hari Ini</h3>
                    <a href="{{ route('absensi.index') }}" class="text-xs text-secondary hover:text-secondary-container hover:underline transition-colors font-medium">Buka Absensi →</a>
                </div>
                @if($absensiHariIni->count() > 0)
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-mk-borderLight bg-mk-surface">
                            <th class="px-4 py-2.5 text-left text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Jam</th>
                            <th class="px-4 py-2.5 text-left text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Murid</th>
                            <th class="px-4 py-2.5 text-left text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Guru</th>
                            <th class="px-4 py-2.5 text-left text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Ruangan</th>
                            <th class="px-4 py-2.5 text-center text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($absensiHariIni as $sesi)
                        <tr class="border-b border-mk-borderLight hover:bg-mk-surface/40 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-mk-muted">
                                {{ $sesi->schedule ? \Carbon\Carbon::parse($sesi->schedule->start_time)->format('H:i') : '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('students.show', $sesi->student_id) }}" class="text-secondary hover:text-secondary-container hover:underline font-semibold">
                                    {{ $sesi->student->full_name ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2.5 text-mk-muted">
                                {{ $sesi->teacher->name ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-mk-muted font-medium">
                                {{ $sesi->schedule?->room?->code ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#c47a45]/10 text-[#c47a45]">
                                    Belum Diabsen
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="px-5 py-8 text-center text-mk-dim text-sm bg-mk-bg/5">Tidak ada sesi yang perlu diabsen hari ini.</div>
                @endif
            </div>
            @else
            {{-- Statistik Murid (Owner + Auditor) --}}
            <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden fade-in-up" style="animation-delay:320ms">
                <div class="px-5 py-4 border-b border-mk-borderLight flex justify-between items-center bg-mk-bg/30">
                    <h3 class="text-sm font-semibold text-mk-text">Statistik Murid</h3>
                    <a href="{{ route('students.index') }}" class="text-xs text-secondary hover:text-secondary-container hover:underline transition-colors font-medium">Lihat semua →</a>
                </div>
                <div class="p-5 space-y-3">
                    @foreach([
                        ['Aktif', $muridAktif, 'rgba(44,105,78,0.12)',  '#2C694E'],
                        ['Trial', $muridTrial, 'rgba(167,139,250,0.12)', '#A78BFA'],
                        ['Cuti',  $muridCuti,  'rgba(196,122,69,0.12)',  '#C47A45'],
                        ['Calon', $muridCalon, 'rgba(122,56,24,0.12)',  '#7A3818'],
                    ] as [$lbl, $cnt, $bg, $clr])
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full inline-block" style="background:{{ $clr }}"></span>
                            <span class="text-sm text-mk-muted font-medium">{{ $lbl }}</span>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold"
                              style="background:{{ $bg }};color:{{ $clr }}">
                            {{ $cnt }} murid
                        </span>
                    </div>
                    @endforeach
                    <div class="flex justify-between items-center border-t border-mk-borderLight pt-3 mt-2">
                        <span class="text-sm font-semibold text-mk-muted">Total Terdaftar</span>
                        <span class="text-sm font-bold text-mk-text">{{ $muridTotal }} murid</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- 10 Invoice Terlama Belum Lunas --}}
            <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden fade-in-up" style="animation-delay:360ms">
                <div class="px-5 py-4 border-b border-mk-borderLight flex justify-between items-center bg-mk-bg/30">
                    <h3 class="text-sm font-semibold text-mk-text">Tagihan Belum Lunas</h3>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-primary/5 text-primary tracking-wide uppercase">Terlama</span>
                </div>
                @if($invoiceTerlama->count() > 0)
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-mk-borderLight bg-mk-surface">
                            <th class="px-4 py-2.5 text-left text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Murid</th>
                            @if(!$isAdmin)
                            <th class="px-4 py-2.5 text-right text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Sisa</th>
                            @endif
                            <th class="px-4 py-2.5 text-center text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Jatuh Tempo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoiceTerlama as $inv)
                        <tr class="border-b border-mk-borderLight hover:bg-mk-surface/40 transition-colors">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('invoices.show', $inv) }}" class="text-secondary hover:text-secondary-container hover:underline font-semibold">
                                    {{ $inv->student->full_name ?? '—' }}
                                </a>
                                <div class="text-mk-dim font-mono text-[10px] mt-0.5">{{ $inv->invoice_number }}</div>
                            </td>
                            @if(!$isAdmin)
                            <td class="px-4 py-2.5 text-right font-mono font-semibold text-error">
                                Rp {{ number_format($inv->total_amount - $inv->paid_amount, 0, ',', '.') }}
                            </td>
                            @endif
                            <td class="px-4 py-2.5 text-center">
                                @if($inv->due_date)
                                    @php $late = max(0, (int) now()->startOfDay()->diffInDays($inv->due_date, false) * -1) @endphp
                                    <span class="{{ $late > 0 ? 'text-error font-bold' : 'text-mk-dim font-medium' }}">
                                        {{ $inv->due_date->format('d M') }}
                                        @if($late > 0)<span class="ml-0.5">(+{{ $late }}h)</span>@endif
                                    </span>
                                @else —
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="px-5 py-8 text-center text-mk-dim text-sm bg-mk-bg/5">Tidak ada piutang aktif.</div>
                @endif
            </div>
        </div>

        @if(!$isAdmin)
        {{-- ===== BARIS 5: HONOR BELUM DIBAYAR ===== --}}
        <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden fade-in-up" style="animation-delay:400ms">
            <div class="px-5 py-4 border-b border-mk-borderLight flex justify-between items-center bg-mk-bg/30">
                <h3 class="text-sm font-semibold text-mk-text">Slip Honor Belum Dibayarkan</h3>
                @if($isOwner)
                <a href="{{ route('honors.index') }}" class="text-xs text-secondary hover:text-secondary-container hover:underline transition-colors font-medium">Kelola honor →</a>
                @endif
            </div>
            @if($honorBelumBayar->count() > 0)
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-mk-borderLight bg-mk-surface">
                        <th class="px-4 py-2.5 text-left text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Guru</th>
                        <th class="px-4 py-2.5 text-center text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Periode</th>
                        <th class="px-4 py-2.5 text-right text-mk-dim font-semibold uppercase tracking-wide text-[10px]">Total Honor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($honorBelumBayar as $slip)
                    <tr class="border-b border-mk-borderLight hover:bg-mk-surface/40 transition-colors">
                        <td class="px-4 py-2.5 font-medium">
                            <a href="{{ route('honors.show', $slip) }}" class="text-secondary hover:text-secondary-container hover:underline font-semibold">
                                {{ $slip->teacher->name }}
                            </a>
                        </td>
                        <td class="px-4 py-2.5 text-center text-mk-dim font-medium">
                            {{ str_pad($slip->month, 2, '0', STR_PAD_LEFT) }}/{{ $slip->year }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono font-semibold text-secondary">
                            Rp {{ number_format($slip->total_honor, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="px-5 py-8 text-center text-mk-dim text-sm bg-mk-bg/5">Semua honor sudah dibayarkan. ✓</div>
            @endif
        </div>
        @endif

    </div>

    {{-- ===== INISIALISASI APEXCHARTS ===== --}}
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // Warna chart — theme-aware (dark/light sesuai preferensi user)
        const isDark = (localStorage.getItem('mk-theme') || 'dark') === 'dark';
        const chartLabelColor  = isDark ? '#8B92A8' : '#9A7050';
        const chartGridColor   = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(101,65,27,0.08)';
        const chartTooltipTheme = isDark ? 'dark' : 'light';
        const chartTooltipBg    = isDark ? '#1E2235' : '#FBF5EC';

        // ---- Area Chart: P&L 6 Bulan (Owner only) ----
        @if($isOwner)
        const revenueData = @json($revenueChart);

        new ApexCharts(document.getElementById('chart-revenue'), {
            chart: {
                type: 'area', height: 200,
                background: 'transparent', toolbar: { show: false },
                fontFamily: '"Hanken Grotesk", "DM Sans", sans-serif',
                animations: { enabled: true, easing: 'easeinout', speed: 600 },
            },
            series: [
                { name: 'Pemasukan',   data: revenueData.map(d => d.pemasukan) },
                { name: 'Honor',       data: revenueData.map(d => d.honor) },
                { name: 'Pengeluaran', data: revenueData.map(d => d.pengeluaran) },
            ],
            xaxis: {
                categories: revenueData.map(d => d.label),
                labels: { style: { colors: chartLabelColor, fontSize: '11px' } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { colors: chartLabelColor, fontSize: '11px' },
                    formatter: v => 'Rp ' + (v / 1000000).toFixed(1) + 'jt',
                },
            },
            colors: ['#5DB890', '#C47A45', '#BA1A1A'],
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 95] },
            },
            stroke: { curve: 'smooth', width: 2 },
            grid: { borderColor: chartGridColor, strokeDashArray: 4 },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: {
                theme: chartTooltipTheme,
                y: { formatter: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) },
            },
        }).render();

        // ---- Donut Chart: Distribusi Instrumen ----
        const instrumenData = @json($instrumenChart);
        if (instrumenData.length > 0) {
            const instrumenColors = ['#D4A853','#60A5FA','#A78BFA','#34D399','#FBBF24','#F87171','#FB7185','#2DD4BF'];
            new ApexCharts(document.getElementById('chart-instrumen'), {
                chart: {
                    type: 'donut', height: 170,
                    background: 'transparent',
                    fontFamily: '"Hanken Grotesk", "DM Sans", sans-serif',
                    animations: { enabled: true, easing: 'easeinout', speed: 600 },
                },
                series: instrumenData.map(d => d.total),
                labels: instrumenData.map(d => d.name),
                colors: instrumenColors.slice(0, instrumenData.length),
                plotOptions: {
                    pie: { donut: { size: '62%', labels: { show: false } } },
                },
                dataLabels: { enabled: false },
                legend: { show: false },
                tooltip: {
                    theme: chartTooltipTheme,
                    y: { formatter: v => v + ' murid' },
                },
                stroke: { width: 0 },
            }).render();
        }
        @endif

        // ---- Bar Chart: Absensi Mingguan (semua role) ----
        const attendanceData = @json($attendanceChart);

        new ApexCharts(document.getElementById('chart-attendance'), {
            chart: {
                type: 'bar', height: 180,
                background: 'transparent', toolbar: { show: false },
                fontFamily: '"Hanken Grotesk", "DM Sans", sans-serif',
                animations: { enabled: true, easing: 'easeinout', speed: 600 },
            },
            series: [
                { name: 'Hadir',  data: attendanceData.map(d => d.hadir) },
                { name: 'Izin',   data: attendanceData.map(d => d.izin) },
                { name: 'Hangus', data: attendanceData.map(d => d.hangus) },
            ],
            xaxis: {
                categories: attendanceData.map(d => d.label),
                labels: { style: { colors: chartLabelColor, fontSize: '11px' } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: chartLabelColor, fontSize: '11px' } } },
            colors: ['#5DB890', '#C47A45', '#BA1A1A'],
            plotOptions: {
                bar: { borderRadius: 4, columnWidth: '60%' },
            },
            grid: { borderColor: chartGridColor, strokeDashArray: 4 },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: { theme: chartTooltipTheme },
        }).render();

    });
    </script>
    @endpush
</x-app-layout>
