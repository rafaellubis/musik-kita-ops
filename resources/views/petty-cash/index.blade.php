<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Petty Cash</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $monthName }}</div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('petty-cash.print', ['year' => $year, 'month' => $month]) }}"
                   target="_blank"
                   class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                    Cetak PDF
                </a>
                @role('Owner')
                <a href="{{ route('petty-cash.topups.create') }}"
                   class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary">
                    + Isi Saldo
                </a>
                @endrole
                @hasanyrole('Owner|Admin')
                <a href="{{ route('petty-cash.expenses.create') }}"
                   class="px-4 py-2 rounded-lg text-sm font-bold transition-colors bg-red-600 hover:bg-red-700 text-white">
                    + Catat Pengeluaran Petty Cash
                </a>
                @endhasanyrole
            </div>
        </div>
    </x-slot>

    @php $isOwner = auth()->user()?->hasRole('Owner'); @endphp

    <div class="py-6 px-4 lg:px-8 space-y-4">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- Saldo tersedia --}}
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-5">
            <h3 class="text-sm font-semibold text-mk-muted mb-2">Saldo Petty Cash Tersedia</h3>
            <div class="text-3xl font-bold {{ $balance >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                Rp {{ number_format($balance, 0, ',', '.') }}
            </div>
            <p class="text-xs text-mk-dim mt-1">Total isi saldo dikurangi total pengeluaran petty cash</p>
        </div>

        {{-- Filter bulan --}}
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs text-mk-dim mb-1">Tahun</label>
                    <select name="year" class="border-mk-border rounded text-sm" onchange="this.form.submit()">
                        @foreach(range(now()->year - 1, now()->year + 1) as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-mk-dim mb-1">Bulan</label>
                    <select name="month" class="border-mk-border rounded text-sm" onchange="this.form.submit()">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create(null, $m)->format('M') }} ({{ $m }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        {{-- Ringkasan bulan --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-green-50 rounded-lg p-4 border border-green-100">
                <div class="text-xs text-mk-dim">Isi Saldo Bulan Ini</div>
                <div class="text-lg font-bold text-green-700 mt-1">
                    + Rp {{ number_format($topupTotal, 0, ',', '.') }}
                </div>
            </div>
            <div class="bg-red-50 rounded-lg p-4 border border-red-100">
                <div class="text-xs text-mk-dim">Pengeluaran Petty Cash Bulan Ini</div>
                <div class="text-lg font-bold text-red-700 mt-1">
                    − Rp {{ number_format($expenseTotal, 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- Tabel mutasi --}}
        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-mk-muted">Mutasi {{ $monthName }}</h3>
            </div>
            @if($mutations->isEmpty())
                <div class="p-8 text-center text-sm text-mk-dim">
                    Belum ada mutasi petty cash pada periode ini.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-mk-dim">Tanggal</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-mk-dim">No. Referensi</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-mk-dim">Keterangan</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-mk-dim">Nominal</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-mk-dim">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($mutations as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($row->date)->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $row->number }}</td>
                                    <td class="px-4 py-2">
                                        {{ $row->description }}
                                        @if($row->type === 'expense' && isset($row->model->category))
                                            <span class="text-xs text-mk-dim"> · {{ $row->model->category->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold whitespace-nowrap
                                        {{ $row->type === 'topup' ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $row->type === 'topup' ? '+' : '−' }}
                                        Rp {{ number_format($row->amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        @if($row->type === 'topup')
                                            <a href="{{ route('petty-cash.topups.show', $row->model) }}"
                                               class="text-indigo-600 hover:underline text-xs">Detail</a>
                                        @else
                                            <a href="{{ route('petty-cash.expenses.show', $row->model) }}"
                                               class="text-indigo-600 hover:underline text-xs">Detail</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
