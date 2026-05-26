<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-xs text-gray-500 mb-0.5">Selamat datang, {{ auth()->user()->name }}</div>
                <h2 class="font-semibold text-xl text-gray-800">Dashboard — {{ $monthName }}</h2>
            </div>
            <div class="flex gap-2">
                @if($isOwner)
                <a href="{{ route('reports.finance', ['year' => $year, 'month' => $month]) }}"
                   class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-medium transition-colors">
                    Laporan Keuangan →
                </a>
                @endif
                <a href="{{ route('reports.students', ['year' => $year, 'month' => $month]) }}"
                   class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-medium transition-colors">
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
            <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm fade-in-up" style="animation-delay:0ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-widest font-semibold mb-2">Pendapatan Bulan Ini</div>
                        <div class="text-2xl font-bold text-green-700 leading-none">
                            Rp {{ number_format($revenueBulan, 0, ',', '.') }}
                        </div>
                        <div class="mt-1.5 text-xs text-gray-400">
                            Cash {{ number_format($revenueCash/1000000, 1, ',', '.') }}jt
                            · Transfer {{ number_format($revenueTransfer/1000000, 1, ',', '.') }}jt
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0"
                         style="background:rgba(52,211,153,0.15)">💰</div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm fade-in-up" style="animation-delay:60ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-widest font-semibold mb-2">Pengeluaran Bulan Ini</div>
                        <div class="text-2xl font-bold text-red-600 leading-none">
                            Rp {{ number_format($pengeluaranBulan, 0, ',', '.') }}
                        </div>
                        <div class="mt-1.5 text-xs text-gray-400">
                            Cash {{ number_format($pengeluaranCash/1000000, 1, ',', '.') }}jt bulan ini
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0"
                         style="background:rgba(248,113,113,0.15)">📉</div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm fade-in-up" style="animation-delay:120ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-widest font-semibold mb-2">Laba / Rugi</div>
                        <div class="text-2xl font-bold leading-none {{ $labaBulan >= 0 ? 'text-green-700' : 'text-red-600' }}">
                            {{ $labaBulan < 0 ? '-' : '' }}Rp {{ number_format(abs($labaBulan), 0, ',', '.') }}
                        </div>
                        <div class="mt-1.5 text-xs {{ $labaBulan >= 0 ? 'text-green-600' : 'text-red-500' }}">
                            {{ $labaBulan >= 0 ? '↑ Surplus' : '↓ Defisit' }} bulan ini
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0"
                         style="background:{{ $labaBulan >= 0 ? 'rgba(52,211,153,0.15)' : 'rgba(248,113,113,0.15)' }}">
                        {{ $labaBulan >= 0 ? '📈' : '📉' }}
                    </div>
                </div>
            </div>
            @endif

            {{-- Saldo Kas (semua role) --}}
            <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm fade-in-up"
                 style="animation-delay:{{ $isOwner ? '180ms' : '0ms' }}">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-widest font-semibold mb-2">Saldo Kas</div>
                        <div class="text-2xl font-bold leading-none {{ $saldoKas >= 0 ? 'text-blue-700' : 'text-red-600' }}">
                            Rp {{ number_format(abs($saldoKas), 0, ',', '.') }}
                        </div>
                        <div class="mt-1.5 text-xs text-gray-400">
                            Masuk {{ number_format($kasmasukTotal/1000000, 1, ',', '.') }}jt
                            · Keluar {{ number_format($kaskeluarTotal/1000000, 1, ',', '.') }}jt
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0"
                         style="background:rgba(96,165,250,0.15)">💵</div>
                </div>
            </div>

            {{-- Murid Aktif untuk non-Owner agar row 1 tetap 2 kartu --}}
            @if(!$isOwner)
            <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm fade-in-up" style="animation-delay:60ms">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-widest font-semibold mb-2">Murid Aktif</div>
                        <div class="text-2xl font-bold text-indigo-700 leading-none">{{ $muridAktif }}</div>
                        <div class="mt-1.5 text-xs text-gray-400">
                            Trial {{ $muridTrial }} · Cuti {{ $muridCuti }} · Calon {{ $muridCalon }}
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0"
                         style="background:rgba(129,140,248,0.15)">🎓</div>
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- ===== BARIS 2: CHART P&L + DONUT INSTRUMEN (Owner only) ===== --}}
        @if($isOwner)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 fade-in-up" style="animation-delay:200ms">

            {{-- Area Chart: P&L 6 Bulan --}}
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Laporan Keuangan</div>
                        <div class="text-xs text-gray-500 mt-0.5">6 bulan terakhir</div>
                    </div>
                    <div class="flex items-center gap-4">
                        @foreach([['#D4A853','Pemasukan'],['#60A5FA','Honor'],['#F87171','Pengeluaran']] as [$clr,$lbl])
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <span class="w-2 h-2 rounded-sm inline-block shrink-0" style="background:{{ $clr }}"></span>{{ $lbl }}
                        </div>
                        @endforeach
                    </div>
                </div>
                <div id="chart-revenue"></div>
            </div>

            {{-- Donut: Distribusi Instrumen --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-sm font-semibold text-gray-800 mb-0.5">Distribusi Instrumen</div>
                <div class="text-xs text-gray-500 mb-2">{{ $muridAktif }} murid aktif</div>
                <div id="chart-instrumen"></div>
                @php $instrumenColors = ['#D4A853','#60A5FA','#A78BFA','#34D399','#FBBF24','#F87171','#FB7185','#2DD4BF']; @endphp
                <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 mt-1">
                    @foreach($instrumenChart as $idx => $ins)
                    <div class="flex items-center gap-1.5 text-xs min-w-0">
                        <span class="w-2 h-2 rounded-sm shrink-0 inline-block"
                              style="background:{{ $instrumenColors[$idx % count($instrumenColors)] }}"></span>
                        <span class="text-gray-500 truncate">{{ $ins->name }}</span>
                        <span class="text-gray-800 font-semibold ml-auto shrink-0">{{ $ins->total }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- ===== BARIS 3: BAR CHART ABSENSI + AGING PIUTANG ===== --}}
        <div class="grid grid-cols-1 {{ $isAdmin ? '' : 'lg:grid-cols-2' }} gap-5">

            {{-- Bar Chart: Absensi Mingguan --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 fade-in-up" style="animation-delay:240ms">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Absensi Bulan Ini</div>
                        <div class="text-xs text-gray-500 mt-0.5">Per minggu</div>
                    </div>
                    <div class="flex items-center gap-4">
                        @foreach([['#34D399','Hadir'],['#FBBF24','Izin'],['#F87171','Hangus']] as [$clr,$lbl])
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <span class="w-2 h-2 rounded-sm inline-block shrink-0" style="background:{{ $clr }}"></span>{{ $lbl }}
                        </div>
                        @endforeach
                    </div>
                </div>
                <div id="chart-attendance"></div>
            </div>

            @if(!$isAdmin)
            {{-- Aging Piutang --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 fade-in-up" style="animation-delay:280ms">
                <div class="flex justify-between items-center mb-4">
                    <div class="text-sm font-semibold text-gray-800">Aging Piutang</div>
                    <a href="{{ route('invoices.index') }}" class="text-xs text-indigo-600 hover:underline">Lihat tagihan →</a>
                </div>
                <div class="space-y-3">
                    @foreach([
                        ['Belum jatuh tempo', $aging['current'],   $agingCount['current'],   'text-gray-600',  'text-gray-800'],
                        ['Telat 1–30 hari',   $aging['late1_30'], $agingCount['late1_30'], 'text-orange-600','text-orange-700'],
                        ['Telat > 30 hari',   $aging['late31'],   $agingCount['late31'],   'text-red-600',   'text-red-700'],
                    ] as [$lbl, $amt, $cnt, $lblCls, $amtCls])
                    <div class="flex items-center justify-between">
                        <span class="text-sm {{ $lblCls }}">{{ $lbl }}</span>
                        <div class="text-right">
                            <div class="text-sm font-medium {{ $amtCls }}">Rp {{ number_format($amt, 0, ',', '.') }}</div>
                            <div class="text-[10px] text-gray-400">{{ $cnt }} tagihan</div>
                        </div>
                    </div>
                    @endforeach
                    <div class="flex justify-between items-center border-t border-gray-100 pt-3 mt-1">
                        <span class="text-sm font-medium text-gray-700">Total Piutang</span>
                        <span class="text-sm font-bold {{ $totalPiutang > 0 ? 'text-red-700' : 'text-gray-700' }}">
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
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden fade-in-up" style="animation-delay:320ms">
                <div class="px-5 py-3.5 border-b border-gray-100 flex justify-between items-center">
                    <div class="text-sm font-semibold text-gray-800">Daftar Absensi Hari Ini</div>
                    <a href="{{ route('absensi.index') }}" class="text-xs text-indigo-600 hover:underline">Buka Absensi →</a>
                </div>
                @if($absensiHariIni->count() > 0)
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="px-4 py-2.5 text-left text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Jam</th>
                            <th class="px-4 py-2.5 text-left text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Murid</th>
                            <th class="px-4 py-2.5 text-left text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Guru</th>
                            <th class="px-4 py-2.5 text-left text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Ruangan</th>
                            <th class="px-4 py-2.5 text-center text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($absensiHariIni as $sesi)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-gray-600">
                                {{ $sesi->schedule ? \Carbon\Carbon::parse($sesi->schedule->start_time)->format('H:i') : '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('students.show', $sesi->student_id) }}" class="text-indigo-600 hover:underline font-medium">
                                    {{ $sesi->student->full_name ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2.5 text-gray-600">
                                {{ $sesi->teacher->name ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-600">
                                {{ $sesi->schedule?->room?->code ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-50 text-yellow-700">
                                    Belum Diabsen
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada sesi yang perlu diabsen hari ini.</div>
                @endif
            </div>
            @else
            {{-- Statistik Murid (Owner + Auditor) --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 fade-in-up" style="animation-delay:320ms">
                <div class="flex justify-between items-center mb-4">
                    <div class="text-sm font-semibold text-gray-800">Statistik Murid</div>
                    <a href="{{ route('students.index') }}" class="text-xs text-indigo-600 hover:underline">Lihat semua →</a>
                </div>
                <div class="space-y-2.5">
                    @foreach([
                        ['Aktif', $muridAktif, 'rgba(52,211,153,0.12)',  '#34D399'],
                        ['Trial', $muridTrial, 'rgba(167,139,250,0.12)', '#A78BFA'],
                        ['Cuti',  $muridCuti,  'rgba(251,191,36,0.12)',  '#FBBF24'],
                        ['Calon', $muridCalon, 'rgba(139,146,168,0.12)', '#8B92A8'],
                    ] as [$lbl, $cnt, $bg, $clr])
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full inline-block" style="background:{{ $clr }}"></span>
                            <span class="text-sm text-gray-600">{{ $lbl }}</span>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold"
                              style="background:{{ $bg }};color:{{ $clr }}">
                            {{ $cnt }} murid
                        </span>
                    </div>
                    @endforeach
                    <div class="flex justify-between items-center border-t border-gray-100 pt-2.5 mt-1">
                        <span class="text-sm font-medium text-gray-700">Total Terdaftar</span>
                        <span class="text-sm font-bold text-gray-900">{{ $muridTotal }} murid</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- 10 Invoice Terlama Belum Lunas --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden fade-in-up" style="animation-delay:360ms">
                <div class="px-5 py-3.5 border-b border-gray-100 flex justify-between items-center">
                    <div class="text-sm font-semibold text-gray-800">Tagihan Belum Lunas</div>
                    <span class="text-[10px] text-gray-400 uppercase tracking-wide">terlama</span>
                </div>
                @if($invoiceTerlama->count() > 0)
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="px-4 py-2.5 text-left text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Murid</th>
                            <th class="px-4 py-2.5 text-right text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Sisa</th>
                            <th class="px-4 py-2.5 text-center text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Jatuh Tempo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoiceTerlama as $inv)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('invoices.show', $inv) }}" class="text-indigo-600 hover:underline font-medium">
                                    {{ $inv->student->full_name ?? '—' }}
                                </a>
                                <div class="text-gray-400 font-mono text-[10px]">{{ $inv->invoice_number }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono font-semibold" style="color:#F87171">
                                Rp {{ number_format($inv->total_amount - $inv->paid_amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @if($inv->due_date)
                                    @php $late = max(0, (int) now()->startOfDay()->diffInDays($inv->due_date, false) * -1) @endphp
                                    <span style="color:{{ $late > 0 ? '#F87171' : '#6B7494' }}"
                                          class="{{ $late > 0 ? 'font-semibold' : '' }}">
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
                <div class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada piutang aktif.</div>
                @endif
            </div>
        </div>

        @if(!$isAdmin)
        {{-- ===== BARIS 5: HONOR BELUM DIBAYAR ===== --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden fade-in-up" style="animation-delay:400ms">
            <div class="px-5 py-3.5 border-b border-gray-100 flex justify-between items-center">
                <div class="text-sm font-semibold text-gray-800">Slip Honor Belum Dibayarkan</div>
                @if($isOwner)
                <a href="{{ route('honors.index') }}" class="text-xs text-indigo-600 hover:underline">Kelola honor →</a>
                @endif
            </div>
            @if($honorBelumBayar->count() > 0)
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="px-4 py-2.5 text-left text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Guru</th>
                        <th class="px-4 py-2.5 text-center text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Periode</th>
                        <th class="px-4 py-2.5 text-right text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Total Honor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($honorBelumBayar as $slip)
                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-2.5 font-medium">
                            <a href="{{ route('honors.show', $slip) }}" class="text-indigo-600 hover:underline">
                                {{ $slip->teacher->name }}
                            </a>
                        </td>
                        <td class="px-4 py-2.5 text-center text-gray-500">
                            {{ str_pad($slip->month, 2, '0', STR_PAD_LEFT) }}/{{ $slip->year }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono font-semibold" style="color:#D4A853">
                            Rp {{ number_format($slip->total_honor, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="px-5 py-8 text-center text-gray-400 text-sm">Semua honor sudah dibayarkan. ✓</div>
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
                fontFamily: 'DM Sans, sans-serif',
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
            colors: ['#D4A853', '#60A5FA', '#F87171'],
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
                    fontFamily: 'DM Sans, sans-serif',
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
                fontFamily: 'DM Sans, sans-serif',
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
            colors: ['#34D399', '#FBBF24', '#F87171'],
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
