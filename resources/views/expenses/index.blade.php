<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Pengeluaran & Kas</h2>
                @php $monthName = \Carbon\Carbon::create($year, $month, 1)->format('F Y'); @endphp
                <div class="text-xs text-mk-muted mt-0.5">{{ $monthName }}</div>
            </div>
            @hasanyrole('Owner|Admin')
            <a href="{{ route('expenses.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors"
               style="background:#D4A853;color:#1A1000">
                + Catat Pengeluaran
            </a>
            @endhasanyrole
        </div>
    </x-slot>

    @php $isOwner = auth()->user()?->hasRole('Owner'); @endphp

    <div class="py-6 px-4 lg:px-8 space-y-4">

        @if(session('success'))
        <div class="p-3 rounded-lg text-sm"
             style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="p-3 rounded-lg text-sm"
             style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
            {{ session('error') }}
        </div>
        @endif

        {{-- ===== FILTER ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Tahun</label>
                    <select name="year" class="border-gray-300 rounded text-sm" onchange="this.form.submit()">
                        @foreach(range(now()->year - 1, now()->year + 1) as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Bulan</label>
                    <select name="month" class="border-gray-300 rounded text-sm" onchange="this.form.submit()">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create(null, $m)->format('M') }} ({{ $m }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Kategori</label>
                    <select name="category_id" class="border-gray-300 rounded text-sm" onchange="this.form.submit()">
                        <option value="">Semua</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Metode</label>
                    <select name="method" class="border-gray-300 rounded text-sm" onchange="this.form.submit()">
                        <option value="">Semua</option>
                        <option value="CASH" {{ request('method') == 'CASH' ? 'selected' : '' }}>CASH</option>
                        <option value="TRANSFER" {{ request('method') == 'TRANSFER' ? 'selected' : '' }}>TRANSFER</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Cari</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Keterangan..."
                           class="border-gray-300 rounded text-sm"
                           onchange="this.form.submit()">
                </div>
            </form>
        </div>

        {{-- ===== PETTY CASH HARI INI ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Petty Cash Hari Ini ({{ now()->format('d M Y') }})</h3>
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div class="bg-green-50 rounded-lg p-3 border border-green-100">
                    <div class="text-xs text-gray-500">Kas Masuk (Tunai)</div>
                    <div class="text-lg font-bold text-green-700 mt-1">
                        Rp {{ number_format($kasmasukHariIni, 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-gray-400">Pembayaran SPP tunai hari ini</div>
                </div>
                <div class="bg-red-50 rounded-lg p-3 border border-red-100">
                    <div class="text-xs text-gray-500">Kas Keluar (Tunai)</div>
                    <div class="text-lg font-bold text-red-700 mt-1">
                        Rp {{ number_format($kaskeluarHariIni, 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-gray-400">Pengeluaran CASH hari ini</div>
                </div>
                <div class="bg-blue-50 rounded-lg p-3 border border-blue-100">
                    <div class="text-xs text-gray-500">Saldo Kas Hari Ini</div>
                    @php $saldoHariIni = $kasmasukHariIni - $kaskeluarHariIni; @endphp
                    <div class="text-lg font-bold mt-1 {{ $saldoHariIni >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                        Rp {{ number_format($saldoHariIni, 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-gray-400">Masuk - Keluar</div>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t grid grid-cols-3 gap-4 text-sm">
                <div>
                    <div class="text-xs text-gray-500">Kas Masuk Bulan Ini</div>
                    <div class="font-semibold text-green-700">Rp {{ number_format($kasmasukBulan, 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Total Pengeluaran CASH</div>
                    <div class="font-semibold text-red-700">Rp {{ number_format($kaskeluarBulan, 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Saldo Kas Bulan Ini</div>
                    @php $saldoBulan = $kasmasukBulan - $kaskeluarBulan; @endphp
                    <div class="font-semibold {{ $saldoBulan >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                        Rp {{ number_format($saldoBulan, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== RINGKASAN PER KATEGORI ===== --}}
        @if($summary->isNotEmpty())
        <div class="bg-white shadow-sm sm:rounded-lg p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                Ringkasan per Kategori — {{ $monthName }}
                <span class="ml-2 font-normal text-gray-500">
                    Total: Rp {{ number_format($totalBulan, 0, ',', '.') }}
                </span>
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                @foreach($summary as $row)
                    <div class="bg-gray-50 rounded-lg p-2.5">
                        <div class="text-xs text-gray-500">{{ $row->cat_name }}</div>
                        <div class="font-semibold mt-0.5">Rp {{ number_format($row->total, 0, ',', '.') }}</div>
                        <div class="text-xs text-gray-400">{{ $row->cnt }} transaksi</div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ===== TABEL PENGELUARAN ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if($expenses->isEmpty())
                <div class="p-8 text-center text-gray-500">
                    <p>Belum ada pengeluaran untuk {{ $monthName }}.</p>
                    @hasanyrole('Owner|Admin')
                        <a href="{{ route('expenses.create') }}" class="mt-2 inline-block text-blue-600 hover:underline text-sm">
                            + Catat pengeluaran pertama
                        </a>
                    @endhasanyrole
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="border-b text-xs text-gray-500 uppercase text-left">
                            <th class="px-4 py-3">No.</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Keterangan</th>
                            <th class="px-4 py-3">Metode</th>
                            <th class="px-4 py-3 text-right">Jumlah</th>
                            <th class="px-4 py-3">Dicatat oleh</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expenses as $exp)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ $exp->expense_number }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $exp->expense_date->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700">
                                        {{ $exp->category->name ?? '?' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $exp->description }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs {{ $exp->payment_method === 'CASH' ? 'text-green-700' : 'text-blue-700' }}">
                                        {{ $exp->payment_method }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">
                                    Rp {{ number_format($exp->amount, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $exp->createdBy->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('expenses.show', $exp) }}"
                                       class="text-xs text-blue-600 hover:underline">Detail</a>
                                    @hasanyrole('Owner|Admin')
                                        ·
                                        <a href="{{ route('expenses.edit', $exp) }}"
                                           class="text-xs text-blue-600 hover:underline">Edit</a>
                                    @endhasanyrole
                                    @if($isOwner)
                                        ·
                                        <form method="POST" action="{{ route('expenses.destroy', $exp) }}"
                                              class="inline"
                                              onsubmit="return confirm('Hapus pengeluaran {{ $exp->expense_number }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-xs text-gray-500">
                                {{ $expenses->total() }} transaksi
                            </td>
                            <td class="px-4 py-2 text-right font-bold text-sm">
                                Rp {{ number_format($expenses->sum('amount'), 0, ',', '.') }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="p-4">{{ $expenses->withQueryString()->links() }}</div>
            @endif
        </div>

    </div>
</x-app-layout>
