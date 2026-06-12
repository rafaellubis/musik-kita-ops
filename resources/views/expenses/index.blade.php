<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Pengeluaran Operasional</h2>
                @php $monthName = \Carbon\Carbon::create($year, $month, 1)->format('F Y'); @endphp
                <div class="text-xs text-mk-muted mt-0.5">{{ $monthName }}</div>
            </div>
            @hasanyrole('Owner|Admin')
            <a href="{{ route('expenses.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary"
               >
                + Catat Pengeluaran
            </a>
            @endhasanyrole
        </div>
    </x-slot>

    @php
        $isOwner = auth()->user()?->hasRole('Owner');
    @endphp

    <div class="py-6 px-4 lg:px-8 space-y-4">

        {{-- ===== FILTER ===== --}}
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
                <div>
                    <label class="block text-xs text-mk-dim mb-1">Kategori</label>
                    <select name="category_id" class="border-mk-border rounded text-sm" onchange="this.form.submit()">
                        <option value="">Semua</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-mk-dim mb-1">Metode</label>
                    <select name="method" class="border-mk-border rounded text-sm" onchange="this.form.submit()">
                        <option value="">Semua</option>
                        <option value="CASH" {{ request('method') == 'CASH' ? 'selected' : '' }}>CASH</option>
                        <option value="TRANSFER" {{ request('method') == 'TRANSFER' ? 'selected' : '' }}>TRANSFER</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-mk-dim mb-1">Cari</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Keterangan..."
                           class="border-mk-border rounded text-sm"
                           onchange="this.form.submit()">
                </div>
            </form>
        </div>

        {{-- ===== RINGKASAN PER KATEGORI ===== --}}
        @if($summary->isNotEmpty())
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-5">
            <h3 class="text-sm font-semibold text-mk-muted mb-3">
                Ringkasan per Kategori — {{ $monthName }}
                <span class="ml-2 font-normal text-mk-dim">
                    Total: Rp {{ number_format($totalBulan, 0, ',', '.') }}
                </span>
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                @foreach($summary as $row)
                    <div class="bg-mk-surface rounded-lg p-2.5">
                        <div class="text-xs text-mk-dim">{{ $row->cat_name }}</div>
                        <div class="font-semibold mt-0.5">Rp {{ number_format($row->total, 0, ',', '.') }}</div>
                        <div class="text-xs text-mk-dim">{{ $row->cnt }} transaksi</div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ===== TABEL PENGELUARAN ===== --}}
        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            @if($expenses->isEmpty())
                <div class="p-8 text-center text-mk-dim">
                    <p>Belum ada pengeluaran untuk {{ $monthName }}.</p>
                    @hasanyrole('Owner|Admin')
                        <a href="{{ route('expenses.create') }}" class="mt-2 inline-block text-blue-600 hover:underline text-sm">
                            + Catat pengeluaran pertama
                        </a>
                    @endhasanyrole
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-mk-surface">
                        <tr class="border-b text-xs text-mk-dim uppercase text-left">
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
                            <tr class="border-b hover:bg-mk-surface">
                                <td class="px-4 py-3 font-mono text-xs text-mk-dim">{{ $exp->expense_number }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $exp->expense_date->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-xs bg-mk-surface text-mk-muted">
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
                                <td class="px-4 py-3 text-xs text-mk-dim">{{ $exp->createdBy->name ?? '—' }}</td>
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
                    <tfoot class="bg-mk-surface">
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-xs text-mk-dim">
                                {{ $expenses->total() }} transaksi
                            </td>
                            <td class="px-4 py-2 text-right font-bold text-sm">
                                Rp {{ number_format($expenses->sum('amount'), 0, ',', '.') }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
                <div class="p-4">{{ $expenses->withQueryString()->links() }}</div>
            @endif
        </div>

    </div>
</x-app-layout>
