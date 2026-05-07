<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Tagihan</h2>
    </x-slot>

    @php
        $statusColors = [
            'UNPAID'  => 'bg-red-100 text-red-700',
            'PARTIAL' => 'bg-yellow-100 text-yellow-800',
            'PAID'    => 'bg-green-100 text-green-700',
            'VOID'    => 'bg-gray-100 text-gray-500',
        ];
        $monthName = \Carbon\Carbon::create($year, $month, 1)->format('F Y');
        $canManage = auth()->user()?->hasAnyRole(['Owner', 'Admin']);
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            {{-- ============= STATS PER STATUS + AGING ============= --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-4">
                @foreach(['UNPAID', 'PARTIAL', 'PAID', 'VOID'] as $st)
                    @php
                        $row = $stats[$st] ?? null;
                        $cnt = $row?->cnt ?? 0;
                        $sum = $row?->sum_total ?? 0;
                    @endphp
                    <div class="p-3 rounded {{ $statusColors[$st] }}">
                        <div class="text-xs uppercase">{{ $st }}</div>
                        <div class="text-2xl font-bold">{{ $cnt }}</div>
                        <div class="text-xs">Rp {{ number_format($sum, 0, ',', '.') }}</div>
                    </div>
                @endforeach
                <div class="p-3 rounded bg-orange-100 text-orange-800">
                    <div class="text-xs uppercase">Overdue (semua)</div>
                    <div class="text-2xl font-bold">{{ $overdueCount }}</div>
                    <div class="text-xs">tagihan lewat tempo</div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6"
                 x-data="{ tool: null }">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">Tagihan {{ $monthName }}</h3>
                    @if($canManage)
                        <div class="flex gap-2">
                            <button type="button" @click="tool = tool === 'spp' ? null : 'spp'"
                                    class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded text-sm">
                                Generate SPP
                            </button>
                            <button type="button" @click="tool = tool === 'fines' ? null : 'fines'"
                                    class="px-3 py-1 bg-orange-600 hover:bg-orange-700 text-white rounded text-sm">
                                Apply Denda
                            </button>
                        </div>
                    @endif
                </div>

                @if($canManage)
                    {{-- ===== Form Generate SPP ===== --}}
                    <div x-show="tool === 'spp'" x-cloak
                         class="mb-4 p-4 border border-purple-200 bg-purple-50 rounded">
                        <form method="POST" action="{{ route('invoices.generate-spp') }}"
                              onsubmit="return confirm('Generate invoice SPP untuk bulan terpilih? Idempotent — invoice yang sudah ada tidak duplikat.')">
                            @csrf
                            <h4 class="font-medium text-sm mb-2">Generate SPP Bulanan</h4>
                            <p class="text-xs text-gray-600 mb-3">
                                Terbitkan invoice SPP untuk semua murid Aktif yang belum punya tagihan SPP di bulan target.
                                Jadwal otomatis: tanggal 1 setiap bulan (BR-5.1).
                            </p>
                            <div class="flex items-end gap-3">
                                <div>
                                    <label class="block text-xs">Tahun</label>
                                    <input type="number" name="year" required min="2024" max="2030"
                                           value="{{ now()->addMonth()->year }}"
                                           class="border-gray-300 rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs">Bulan</label>
                                    <input type="number" name="month" required min="1" max="12"
                                           value="{{ now()->addMonth()->month }}"
                                           class="border-gray-300 rounded text-sm w-20">
                                </div>
                                <button type="submit"
                                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded text-sm">
                                    Jalankan Generator
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- ===== Form Apply Denda ===== --}}
                    <div x-show="tool === 'fines'" x-cloak
                         class="mb-4 p-4 border border-orange-200 bg-orange-50 rounded">
                        <form method="POST" action="{{ route('invoices.apply-fines') }}"
                              onsubmit="return confirm('Apply denda untuk invoice yang masih unpaid di bulan terpilih?')">
                            @csrf
                            <h4 class="font-medium text-sm mb-2">Apply Denda Harian</h4>
                            <p class="text-xs text-gray-600 mb-3">
                                Tambah/update item DENDA Rp 5.000/hari untuk invoice UNPAID/PARTIAL.
                                Hari telat dihitung dari tanggal 11 sampai hari ini (BR-5.3).
                                Jadwal otomatis: harian.
                            </p>
                            <div class="flex items-end gap-3">
                                <div>
                                    <label class="block text-xs">Tahun</label>
                                    <input type="number" name="year" required min="2024" max="2030"
                                           value="{{ $year }}"
                                           class="border-gray-300 rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs">Bulan</label>
                                    <input type="number" name="month" required min="1" max="12"
                                           value="{{ $month }}"
                                           class="border-gray-300 rounded text-sm w-20">
                                </div>
                                <button type="submit"
                                        class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded text-sm">
                                    Apply Denda
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- ============= FILTER ============= --}}
                <form method="GET" action="{{ route('invoices.index') }}" class="mb-4">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
                        <input type="number" name="year" value="{{ $year }}" min="2024" max="2030"
                               class="border-gray-300 rounded text-sm" placeholder="Tahun">
                        <select name="month" class="border-gray-300 rounded text-sm">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(2026, $m, 1)->format('F') }}
                                </option>
                            @endfor
                        </select>
                        <select name="status" class="border-gray-300 rounded text-sm">
                            <option value="">Semua Status</option>
                            @foreach(['UNPAID', 'PARTIAL', 'PAID', 'VOID'] as $st)
                                <option value="{{ $st }}"
                                    {{ request('status') == $st ? 'selected' : '' }}>{{ $st }}</option>
                            @endforeach
                        </select>
                        <select name="student_id" class="border-gray-300 rounded text-sm">
                            <option value="">Semua Murid</option>
                            @foreach($students as $s)
                                <option value="{{ $s->id }}"
                                    {{ request('student_id') == $s->id ? 'selected' : '' }}>
                                    {{ $s->student_code }} - {{ $s->full_name }}
                                </option>
                            @endforeach
                        </select>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="border-gray-300 rounded text-sm"
                               placeholder="Cari no. invoice / nama">
                    </div>
                    <div class="mt-2 flex gap-2">
                        <button type="submit" class="px-4 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                            Filter
                        </button>
                        <a href="{{ route('invoices.index') }}" class="px-4 py-1 bg-gray-200 rounded text-sm">
                            Reset
                        </a>
                    </div>
                </form>

                {{-- ============= TABLE ============= --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200 border">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-1 text-left text-xs uppercase">No. Invoice</th>
                                <th class="px-2 py-1 text-left text-xs uppercase">Murid</th>
                                <th class="px-2 py-1 text-left text-xs uppercase">Items</th>
                                <th class="px-2 py-1 text-right text-xs uppercase">Total</th>
                                <th class="px-2 py-1 text-right text-xs uppercase">Saldo</th>
                                <th class="px-2 py-1 text-center text-xs uppercase">Status</th>
                                <th class="px-2 py-1 text-left text-xs uppercase">Jatuh Tempo</th>
                                <th class="px-2 py-1 text-center text-xs uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($invoices as $inv)
                                <tr>
                                    <td class="px-2 py-1 font-mono text-xs">{{ $inv->invoice_number }}</td>
                                    <td class="px-2 py-1">
                                        <a href="{{ route('students.show', $inv->student_id) }}"
                                           class="text-blue-600 hover:underline">
                                            {{ $inv->student->full_name ?? '?' }}
                                        </a>
                                    </td>
                                    <td class="px-2 py-1 text-xs">
                                        @foreach($inv->items as $item)
                                            <span class="inline-block px-1 bg-gray-100 rounded mr-1">{{ $item->item_code }}</span>
                                        @endforeach
                                    </td>
                                    <td class="px-2 py-1 text-right">
                                        Rp {{ number_format($inv->total_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-2 py-1 text-right font-medium
                                        {{ $inv->balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        Rp {{ number_format($inv->balance, 0, ',', '.') }}
                                    </td>
                                    <td class="px-2 py-1 text-center">
                                        <span class="px-2 py-0.5 text-xs rounded {{ $statusColors[$inv->status] ?? '' }}">
                                            {{ $inv->status }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-1 text-xs">
                                        {{ $inv->due_date->format('d M Y') }}
                                        @if($inv->balance > 0 && $inv->due_date->lt(now()))
                                            <div class="text-orange-600 text-xs">
                                                lewat {{ $inv->due_date->diffInDays(now()) }} hari
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1 text-center">
                                        <a href="{{ route('invoices.show', $inv->id) }}"
                                           class="text-blue-600 hover:underline text-xs">Detail</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-2 py-6 text-center text-gray-500">
                                        Tidak ada tagihan untuk periode/filter ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $invoices->links() }}</div>
            </div>
        </div>
    </div>
    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
