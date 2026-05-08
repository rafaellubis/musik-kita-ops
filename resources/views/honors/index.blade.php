<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Slip Honor Guru — {{ $monthName }}</h2>
        </div>
    </x-slot>

    @php
        $statusColors = [
            'DRAFT'      => 'bg-gray-100 text-gray-500 border-gray-300',
            'CALCULATED' => 'bg-blue-100 text-blue-700 border-blue-300',
            'PAID'       => 'bg-green-100 text-green-700 border-green-300',
        ];
        $statusLabels = [
            'DRAFT'      => 'Draft',
            'CALCULATED' => 'Terhitung',
            'PAID'       => 'Dibayarkan',
        ];
        $isOwner = auth()->user()?->hasRole('Owner');
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            {{-- ============= FILTER + ACTIONS ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <form method="GET" class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Tahun</label>
                        <select name="year" class="border-gray-300 rounded text-sm"
                                onchange="this.form.submit()">
                            @foreach(range(now()->year - 1, now()->year + 1) as $y)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Bulan</label>
                        <select name="month" class="border-gray-300 rounded text-sm"
                                onchange="this.form.submit()">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(null, $m, 1)->format('M') }} ({{ $m }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Status</label>
                        <select name="status" class="border-gray-300 rounded text-sm"
                                onchange="this.form.submit()">
                            <option value="">Semua</option>
                            @foreach(['DRAFT' => 'Draft', 'CALCULATED' => 'Terhitung', 'PAID' => 'Dibayarkan'] as $val => $label)
                                <option value="{{ $val }}" {{ request('status') == $val ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            {{-- ============= STATS ============= --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @php
                    $totalAll  = $stats->sum('sum_total');
                    $totalPaid = $stats->get('PAID')?->sum_total ?? 0;
                    $totalCalc = $stats->get('CALCULATED')?->sum_total ?? 0;
                @endphp
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Total Honor Bulan Ini</div>
                    <div class="text-xl font-bold mt-1">Rp {{ number_format($totalAll, 0, ',', '.') }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Sudah Dibayarkan</div>
                    <div class="text-xl font-bold mt-1 text-green-600">Rp {{ number_format($totalPaid, 0, ',', '.') }}</div>
                    <div class="text-xs text-gray-400">{{ $stats->get('PAID')?->cnt ?? 0 }} slip</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Belum Dibayar (Terhitung)</div>
                    <div class="text-xl font-bold mt-1 text-blue-600">Rp {{ number_format($totalCalc, 0, ',', '.') }}</div>
                    <div class="text-xs text-gray-400">{{ $stats->get('CALCULATED')?->cnt ?? 0 }} slip</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Guru Belum Ada Slip</div>
                    <div class="text-xl font-bold mt-1 {{ $missingCount > 0 ? 'text-orange-500' : 'text-gray-400' }}">
                        {{ $missingCount }} guru
                    </div>
                </div>
            </div>

            {{-- ============= KALKULASI BUTTON (Owner) ============= --}}
            @if($isOwner)
                <div class="bg-white shadow-sm sm:rounded-lg p-4 flex items-center gap-4">
                    <form method="POST" action="{{ route('honors.calculate') }}"
                          onsubmit="return confirm('Kalkulasi honor {{ $monthName }} untuk semua guru aktif? Slip yang sudah PAID tidak akan diubah.')">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                            Hitung Honor {{ $monthName }}
                        </button>
                    </form>
                    <p class="text-sm text-gray-500">
                        Generate / update slip untuk semua {{ \App\Models\Teacher::where('is_active', true)->count() }} guru aktif.
                        Slip PAID tidak akan diubah.
                        @if($missingCount > 0)
                            <span class="text-orange-600 font-medium">
                                {{ $missingCount }} guru belum punya slip bulan ini.
                            </span>
                        @endif
                    </p>
                </div>
            @endif

            {{-- ============= TABEL SLIP ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                @if($slips->isEmpty())
                    <div class="p-8 text-center text-gray-500">
                        <p>Belum ada slip honor untuk {{ $monthName }}.</p>
                        @if($isOwner)
                            <p class="text-sm mt-1">Klik tombol "Hitung Honor" di atas untuk membuat slip dari data absensi.</p>
                        @endif
                    </div>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="border-b text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3">No. Slip</th>
                                <th class="px-4 py-3">Guru</th>
                                <th class="px-4 py-3 text-right">Honor Pokok</th>
                                <th class="px-4 py-3 text-right">Transport</th>
                                <th class="px-4 py-3 text-right">Lain-lain</th>
                                <th class="px-4 py-3 text-right font-bold">Total</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slips as $slip)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-500">
                                        {{ $slip->slip_number }}
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        {{ $slip->teacher->name ?? '?' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        Rp {{ number_format($slip->base_honor, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ $slip->transport_honor > 0
                                            ? 'Rp ' . number_format($slip->transport_honor, 0, ',', '.')
                                            : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ $slip->other_honor > 0
                                            ? 'Rp ' . number_format($slip->other_honor, 0, ',', '.')
                                            : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold">
                                        Rp {{ number_format($slip->total_honor, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded text-xs border {{ $statusColors[$slip->status] }}">
                                            {{ $statusLabels[$slip->status] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('honors.show', $slip) }}"
                                           class="text-xs text-blue-600 hover:underline">Detail</a>
                                        @if($isOwner && !$slip->isLocked())
                                            ·
                                            <a href="{{ route('honors.edit', $slip) }}"
                                               class="text-xs text-indigo-600 hover:underline">Edit</a>
                                        @endif
                                        ·
                                        <a href="{{ route('honors.print', $slip) }}" target="_blank"
                                           class="text-xs text-gray-600 hover:underline">Cetak</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="2" class="px-4 py-2 text-xs text-gray-500">
                                    {{ $slips->total() }} slip
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-medium">
                                    Rp {{ number_format($slips->sum('base_honor'), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-medium">
                                    Rp {{ number_format($slips->sum('transport_honor'), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-medium">
                                    Rp {{ number_format($slips->sum('other_honor'), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-bold">
                                    Rp {{ number_format($slips->sum('total_honor'), 0, ',', '.') }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="p-4">
                        {{ $slips->withQueryString()->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
