<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Laporan Keuangan</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $monthName }}</div>
            </div>
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('reports.finance') }}" class="flex items-center gap-2 no-print">
                    <select name="year" class="border-gray-300 rounded text-sm py-1">
                        @foreach(range(now()->year, now()->year - 2) as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                    <select name="month" class="border-gray-300 rounded text-sm py-1">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ Carbon\Carbon::create(null, $m, 1)->format('M') }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="px-3 py-1 bg-gray-200 hover:bg-gray-300 rounded text-sm">Tampil</button>
                </form>
                <button onclick="window.print()"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm no-print">
                    Cetak PDF
                </button>
            </div>
        </div>
    </x-slot>

    <style>
        @media print {
            .no-print { display: none !important; }
            nav, header { display: none !important; }
            .py-6 { padding: 0 !important; }
            .shadow-sm { box-shadow: none !important; }
            @page { margin: 15mm; }
        }
    </style>

    <div class="py-6 px-4 lg:px-8 space-y-4">

        {{-- ===== RINGKASAN P&L ===== --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b">
                <h3 class="font-semibold text-sm text-gray-700">Ringkasan P&L — {{ $monthName }}</h3>
            </div>
            <div class="p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Total Pendapatan (diterima)</span>
                    <span class="font-medium text-green-700">
                        Rp {{ number_format($totalRevenue, 0, ',', '.') }}
                    </span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Total Honor Guru (dibayar)</span>
                    <span class="font-medium text-red-600">
                        (Rp {{ number_format($honorPaid, 0, ',', '.') }})
                    </span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Total Pengeluaran</span>
                    <span class="font-medium text-red-600">
                        (Rp {{ number_format($totalPengeluaran, 0, ',', '.') }})
                    </span>
                </div>
                <div class="flex justify-between font-semibold border-t pt-2">
                    <span>Laba Bersih</span>
                    <span class="{{ $labaBersih >= 0 ? 'text-green-700' : 'text-red-600' }}">
                        Rp {{ number_format($labaBersih, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ===== RINCIAN PENDAPATAN ===== --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b flex justify-between">
                <h3 class="font-semibold text-sm text-gray-700">Pendapatan per Jenis</h3>
                <span class="text-sm font-semibold text-green-700">
                    Rp {{ number_format($totalRevenue, 0, ',', '.') }}
                </span>
            </div>
            <div class="p-4 space-y-1 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Cash</span>
                    <span class="font-mono">Rp {{ number_format($revenueCash, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Transfer</span>
                    <span class="font-mono">Rp {{ number_format($revenueTransfer, 0, ',', '.') }}</span>
                </div>

                @if($revenueByType->count() > 0)
                <div class="border-t mt-3 pt-3">
                    <div class="text-xs text-gray-500 mb-2">Rincian per Item Tagihan:</div>
                    @foreach($revenueByType as $rt)
                    <div class="flex justify-between text-xs text-gray-600 py-0.5">
                        <span>{{ $rt->item_code }}
                            <span class="text-gray-400">({{ $rt->invoice_count }} invoice)</span>
                        </span>
                        <span class="font-mono">Rp {{ number_format($rt->total, 0, ',', '.') }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        {{-- ===== HONOR GURU ===== --}}
        @if($honorSlips->count() > 0)
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b flex justify-between">
                <h3 class="font-semibold text-sm text-gray-700">Honor Guru</h3>
                <span class="text-sm font-semibold">
                    Total: Rp {{ number_format($totalHonor, 0, ',', '.') }}
                    @if($honorPaid < $totalHonor)
                        <span class="text-xs text-orange-600 ml-2">
                            (dibayar: Rp {{ number_format($honorPaid, 0, ',', '.') }})
                        </span>
                    @endif
                </span>
            </div>
            <table class="w-full text-sm">
                <thead class="border-b text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Guru</th>
                        <th class="px-4 py-2 text-right">Honor Pokok</th>
                        <th class="px-4 py-2 text-right">Transport</th>
                        <th class="px-4 py-2 text-right">Total</th>
                        <th class="px-4 py-2 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($honorSlips as $slip)
                    <tr class="border-b">
                        <td class="px-4 py-2 font-medium">{{ $slip->teacher->name }}</td>
                        <td class="px-4 py-2 text-right font-mono text-xs">
                            Rp {{ number_format($slip->base_honor, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2 text-right font-mono text-xs">
                            Rp {{ number_format($slip->transport_honor, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2 text-right font-mono text-xs font-semibold">
                            Rp {{ number_format($slip->total_honor, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if($slip->status === 'PAID')
                                <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Dibayar</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700">{{ $slip->status_label }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="3" class="px-4 py-2 text-sm font-medium">Total Honor</td>
                        <td class="px-4 py-2 text-right font-bold font-mono text-sm">
                            Rp {{ number_format($totalHonor, 0, ',', '.') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

        {{-- ===== PENGELUARAN PER KATEGORI ===== --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b flex justify-between">
                <h3 class="font-semibold text-sm text-gray-700">Pengeluaran per Kategori</h3>
                <span class="text-sm font-semibold text-red-600">
                    Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}
                </span>
            </div>
            @if($expenseByCategory->count() > 0)
            <table class="w-full text-sm">
                <thead class="border-b text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Kategori</th>
                        <th class="px-4 py-2 text-center">Transaksi</th>
                        <th class="px-4 py-2 text-right">Total</th>
                        <th class="px-4 py-2 text-right">%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expenseByCategory as $cat)
                    <tr class="border-b">
                        <td class="px-4 py-2">
                            <span class="font-medium">{{ $cat->cat_name }}</span>
                            <span class="text-xs text-gray-400 ml-1">{{ $cat->cat_code }}</span>
                        </td>
                        <td class="px-4 py-2 text-center text-gray-500">{{ $cat->cnt }}</td>
                        <td class="px-4 py-2 text-right font-mono text-xs">
                            Rp {{ number_format($cat->total, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2 text-right text-gray-500 text-xs">
                            {{ $totalPengeluaran > 0 ? number_format($cat->total / $totalPengeluaran * 100, 1) : 0 }}%
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="px-4 py-6 text-center text-gray-400 text-sm">Tidak ada pengeluaran bulan ini.</div>
            @endif
        </div>

        <div class="text-xs text-gray-400 text-center">
            Dicetak: {{ now()->format('d M Y H:i') }} · Studio Musik KITA
        </div>

    </div>
</x-app-layout>
