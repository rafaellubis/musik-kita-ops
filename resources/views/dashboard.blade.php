<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Dashboard — {{ $monthName }}</h2>
            <div class="flex gap-2 text-sm">
                @if($isOwner)
                <a href="{{ route('reports.finance', ['year' => $year, 'month' => $month]) }}"
                   class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded text-xs">
                    Laporan Keuangan →
                </a>
                @endif
                <a href="{{ route('reports.students', ['year' => $year, 'month' => $month]) }}"
                   class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded text-xs">
                    Laporan Murid →
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ===== BARIS 1: KARTU RINGKASAN ===== --}}
            {{-- P&L (Owner only) + Saldo Kas (semua role) --}}
            <div class="grid grid-cols-2 {{ $isOwner ? 'sm:grid-cols-4' : 'sm:grid-cols-2' }} gap-4">

                @if($isOwner)
                {{-- Revenue --}}
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Pendapatan Bulan Ini</div>
                    <div class="mt-1 text-2xl font-bold text-green-700">
                        Rp {{ number_format($revenueBulan, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-gray-400">
                        Cash Rp {{ number_format($revenueCash, 0, ',', '.') }}
                        · Transfer Rp {{ number_format($revenueTransfer, 0, ',', '.') }}
                    </div>
                </div>

                {{-- Pengeluaran --}}
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Pengeluaran Bulan Ini</div>
                    <div class="mt-1 text-2xl font-bold text-red-600">
                        Rp {{ number_format($pengeluaranBulan, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-gray-400">
                        Cash Rp {{ number_format($pengeluaranCash, 0, ',', '.') }}
                    </div>
                </div>

                {{-- Laba/Rugi --}}
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Laba / Rugi</div>
                    <div class="mt-1 text-2xl font-bold {{ $labaBulan >= 0 ? 'text-green-700' : 'text-red-600' }}">
                        Rp {{ number_format(abs($labaBulan), 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs {{ $labaBulan >= 0 ? 'text-green-600' : 'text-red-500' }}">
                        {{ $labaBulan >= 0 ? 'Surplus' : 'Defisit' }} bulan ini
                    </div>
                </div>
                @endif

                {{-- Saldo Kas (semua role) --}}
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Saldo Kas</div>
                    <div class="mt-1 text-2xl font-bold {{ $saldoKas >= 0 ? 'text-blue-700' : 'text-red-600' }}">
                        Rp {{ number_format(abs($saldoKas), 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-gray-400">
                        Masuk Rp {{ number_format($kasmasukTotal, 0, ',', '.') }}
                        · Keluar Rp {{ number_format($kaskeluarTotal, 0, ',', '.') }}
                    </div>
                </div>

                {{-- Statistik Murid singkat (semua role) --}}
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Murid Aktif</div>
                    <div class="mt-1 text-2xl font-bold text-indigo-700">{{ $muridAktif }}</div>
                    <div class="mt-1 text-xs text-gray-400">
                        Trial {{ $muridTrial }} · Cuti {{ $muridCuti }} · Calon {{ $muridCalon }}
                    </div>
                </div>
            </div>

            {{-- ===== BARIS 2: STATISTIK MURID + AGING PIUTANG (semua role) ===== --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- Statistik Murid --}}
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-semibold text-gray-700">Statistik Murid</h3>
                        <a href="{{ route('students.index') }}" class="text-xs text-indigo-600 hover:underline">Lihat semua →</a>
                    </div>
                    <div class="space-y-2">
                        @foreach([
                            ['Aktif',   $muridAktif,  'bg-green-100 text-green-700'],
                            ['Trial',   $muridTrial,  'bg-yellow-100 text-yellow-700'],
                            ['Cuti',    $muridCuti,   'bg-orange-100 text-orange-700'],
                            ['Calon',   $muridCalon,  'bg-gray-100 text-gray-600'],
                        ] as [$label, $count, $badge])
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">{{ $label }}</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                {{ $count }} murid
                            </span>
                        </div>
                        @endforeach
                        <div class="flex justify-between items-center border-t pt-2 mt-1">
                            <span class="text-sm font-medium text-gray-700">Total Terdaftar</span>
                            <span class="text-sm font-bold text-gray-900">{{ $muridTotal }} murid</span>
                        </div>
                    </div>
                </div>

                {{-- Aging Piutang (semua role) --}}
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-semibold text-gray-700">Aging Piutang</h3>
                        <a href="{{ route('invoices.index') }}" class="text-xs text-indigo-600 hover:underline">Lihat tagihan →</a>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Belum jatuh tempo</span>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-800">
                                    Rp {{ number_format($aging['current'], 0, ',', '.') }}
                                </div>
                                <div class="text-xs text-gray-400">{{ $agingCount['current'] }} tagihan</div>
                            </div>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-orange-600">Telat 1–30 hari</span>
                            <div class="text-right">
                                <div class="text-sm font-medium text-orange-700">
                                    Rp {{ number_format($aging['late1_30'], 0, ',', '.') }}
                                </div>
                                <div class="text-xs text-gray-400">{{ $agingCount['late1_30'] }} tagihan</div>
                            </div>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-red-600">Telat >30 hari</span>
                            <div class="text-right">
                                <div class="text-sm font-medium text-red-700">
                                    Rp {{ number_format($aging['late31'], 0, ',', '.') }}
                                </div>
                                <div class="text-xs text-gray-400">{{ $agingCount['late31'] }} tagihan</div>
                            </div>
                        </div>
                        <div class="flex justify-between items-center border-t pt-2 mt-1">
                            <span class="text-sm font-medium text-gray-700">Total Piutang</span>
                            <span class="text-sm font-bold {{ $totalPiutang > 0 ? 'text-red-700' : 'text-gray-700' }}">
                                Rp {{ number_format($totalPiutang, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== BARIS 3: TAGIHAN TERLAMA + HONOR BELUM BAYAR (semua role) ===== --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- 10 Invoice Terlama Belum Lunas --}}
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h3 class="text-sm font-semibold text-gray-700">Tagihan Belum Lunas (terlama)</h3>
                    </div>
                    @if($invoiceTerlama->count() > 0)
                    <table class="w-full text-xs">
                        <thead class="border-b text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Murid</th>
                                <th class="px-3 py-2 text-right">Sisa</th>
                                <th class="px-3 py-2 text-center">Jatuh Tempo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoiceTerlama as $inv)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2">
                                    <a href="{{ route('invoices.show', $inv) }}"
                                       class="text-indigo-600 hover:underline">
                                        {{ $inv->student->full_name ?? '—' }}
                                    </a>
                                    <div class="text-gray-400 font-mono">{{ $inv->invoice_number }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-red-700">
                                    Rp {{ number_format($inv->total_amount - $inv->paid_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if($inv->due_date)
                                        @php $late = max(0, (int) now()->startOfDay()->diffInDays($inv->due_date, false) * -1) @endphp
                                        <span class="{{ $late > 0 ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                            {{ $inv->due_date->format('d M') }}
                                            @if($late > 0)
                                                <span class="text-red-500">(+{{ $late }}h)</span>
                                            @endif
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <div class="px-4 py-6 text-center text-gray-400 text-sm">Tidak ada piutang.</div>
                    @endif
                </div>

                {{-- Slip Honor Belum Dibayar (semua role) --}}
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h3 class="text-sm font-semibold text-gray-700">Slip Honor Belum Dibayarkan</h3>
                    </div>
                    @if($honorBelumBayar->count() > 0)
                    <table class="w-full text-xs">
                        <thead class="border-b text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Guru</th>
                                <th class="px-3 py-2 text-center">Periode</th>
                                <th class="px-3 py-2 text-right">Total Honor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($honorBelumBayar as $slip)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium">
                                    <a href="{{ route('honors.show', $slip) }}"
                                       class="text-indigo-600 hover:underline">
                                        {{ $slip->teacher->name }}
                                    </a>
                                </td>
                                <td class="px-3 py-2 text-center text-gray-500">
                                    {{ str_pad($slip->month, 2, '0', STR_PAD_LEFT) }}/{{ $slip->year }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-medium">
                                    Rp {{ number_format($slip->total_honor, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <div class="px-4 py-6 text-center text-gray-400 text-sm">Semua honor sudah dibayarkan.</div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
