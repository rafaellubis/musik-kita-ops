<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">{{ $staffPayroll->slip_number }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $staffPayroll->employee->full_name ?? '?' }} · {{ $monthName }}
                </div>
            </div>
            <a href="{{ route('staff-payrolls.index', ['year' => $staffPayroll->year, 'month' => $staffPayroll->month]) }}"
               class="text-sm text-mk-muted hover:text-mk-text">← Kembali</a>
        </div>
    </x-slot>

    @php
        $statusColors = [
            'DRAFT'      => 'bg-gray-100 text-gray-500 border-gray-300',
            'CALCULATED' => 'bg-blue-100 text-blue-700 border-blue-300',
            'PAID'       => 'bg-green-100 text-green-700 border-green-300',
        ];
        $isOwner = auth()->user()?->hasRole('Owner');
        $allowances = $staffPayroll->items->whereIn('item_type', ['ALLOWANCE', 'OVERTIME']);
        $deductions = $staffPayroll->items->where('item_type', 'DEDUCTION');
    @endphp

    <div class="py-6 px-4 lg:px-8 space-y-4">

        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6">
            <div class="flex justify-between items-start flex-wrap gap-4">
                <div>
                    <div class="font-mono text-sm text-mk-dim">{{ $staffPayroll->slip_number }}</div>
                    <div class="text-2xl font-bold mt-1">{{ $staffPayroll->employee->full_name }}</div>
                    <div class="text-mk-muted text-sm mt-1">
                        {{ $staffPayroll->employee->position }} · Gaji {{ $monthName }}
                    </div>
                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        <span class="px-3 py-1 rounded text-sm border {{ $statusColors[$staffPayroll->status] }}">
                            {{ $staffPayroll->status_label }}
                        </span>
                        @if($staffPayroll->status === 'PAID' && $staffPayroll->paid_at)
                            <span class="text-sm text-mk-dim">
                                Dibayar {{ $staffPayroll->paid_at->format('d M Y') }}
                                @if($staffPayroll->paidBy) oleh {{ $staffPayroll->paidBy->name }} @endif
                            </span>
                        @endif
                    </div>
                    @if($staffPayroll->expense)
                        <div class="mt-2 text-sm">
                            <a href="{{ route('expenses.show', $staffPayroll->expense) }}"
                               class="text-indigo-600 hover:underline">
                                Pengeluaran: {{ $staffPayroll->expense->expense_number }}
                            </a>
                        </div>
                    @endif
                </div>
                <div class="flex gap-2 flex-wrap">
                    <a href="{{ route('staff-payrolls.print', $staffPayroll) }}" target="_blank"
                       class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">Cetak Slip</a>
                    @if($isOwner && $staffPayroll->status === 'CALCULATED')
                        <form method="POST" action="{{ route('staff-payrolls.mark-paid', $staffPayroll) }}"
                              onsubmit="return confirm('Tandai slip sebagai DIBAYAR? Pengeluaran GAJI_STAFF akan dibuat otomatis.')">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded text-sm font-bold"
                                    style="background:#16A34A;color:#fff">Tandai Dibayar</button>
                        </form>
                    @endif
                    @if($isOwner && $staffPayroll->status === 'PAID')
                        <form method="POST" action="{{ route('staff-payrolls.void-paid', $staffPayroll) }}"
                              onsubmit="return confirm('Void pembayaran? Pengeluaran terlink akan dihapus dan slip kembali Terhitung.')">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm">
                                Void Pembayaran
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4 space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-mk-muted">Gaji Pokok</span>
                    <span class="font-mono">Rp {{ number_format($staffPayroll->base_salary, 0, ',', '.') }}</span></div>
                <div class="flex justify-between"><span class="text-mk-muted">Total Tunjangan + Lembur</span>
                    <span class="font-mono text-green-700">+ Rp {{ number_format($staffPayroll->total_allowances, 0, ',', '.') }}</span></div>
                <div class="flex justify-between"><span class="text-mk-muted">Total Potongan</span>
                    <span class="font-mono text-red-600">- Rp {{ number_format($staffPayroll->total_deductions, 0, ',', '.') }}</span></div>
                <div class="flex justify-between font-bold border-t pt-2 text-base">
                    <span>Gaji Bersih</span>
                    <span>Rp {{ number_format($staffPayroll->net_salary, 0, ',', '.') }}</span>
                </div>
            </div>

            @if($staffPayroll->employee->bank_account)
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4 text-sm">
                <div class="text-xs text-mk-dim mb-2">Rekening Transfer</div>
                <div>{{ $staffPayroll->employee->bank_name ?? '—' }}</div>
                <div class="font-mono">{{ $staffPayroll->employee->bank_account }}</div>
                <div class="text-mk-muted">{{ $staffPayroll->employee->bank_account_holder }}</div>
            </div>
            @endif
        </div>

        @if($allowances->isNotEmpty())
        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            <div class="px-4 py-3 bg-mk-surface border-b font-semibold text-sm">Tunjangan & Lembur</div>
            <table class="w-full text-sm">
                <thead class="text-xs text-mk-dim uppercase">
                    <tr class="border-b"><th class="px-4 py-2 text-left">Kode</th><th class="px-4 py-2 text-left">Keterangan</th>
                        <th class="px-4 py-2 text-right">Nominal</th>@if($isOwner && !$staffPayroll->isLocked())<th></th>@endif</tr>
                </thead>
                <tbody>
                    @foreach($allowances as $item)
                    <tr class="border-b">
                        <td class="px-4 py-2">{{ $item->item_code_label }}</td>
                        <td class="px-4 py-2">{{ $item->description }}</td>
                        <td class="px-4 py-2 text-right font-mono">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                        @if($isOwner && !$staffPayroll->isLocked())
                        <td class="px-4 py-2 text-right">
                            <form method="POST" action="{{ route('staff-payrolls.items.destroy', [$staffPayroll, $item]) }}"
                                  onsubmit="return confirm('Hapus komponen ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($deductions->isNotEmpty())
        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            <div class="px-4 py-3 bg-mk-surface border-b font-semibold text-sm">Potongan</div>
            <table class="w-full text-sm">
                <thead class="text-xs text-mk-dim uppercase">
                    <tr class="border-b"><th class="px-4 py-2 text-left">Kode</th><th class="px-4 py-2 text-left">Keterangan</th>
                        <th class="px-4 py-2 text-right">Nominal</th>@if($isOwner && !$staffPayroll->isLocked())<th></th>@endif</tr>
                </thead>
                <tbody>
                    @foreach($deductions as $item)
                    <tr class="border-b">
                        <td class="px-4 py-2">{{ $item->item_code_label }}</td>
                        <td class="px-4 py-2">{{ $item->description }}</td>
                        <td class="px-4 py-2 text-right font-mono text-red-600">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                        @if($isOwner && !$staffPayroll->isLocked())
                        <td class="px-4 py-2 text-right">
                            <form method="POST" action="{{ route('staff-payrolls.items.destroy', [$staffPayroll, $item]) }}"
                                  onsubmit="return confirm('Hapus potongan ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($isOwner && !$staffPayroll->isLocked())
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6">
            <h3 class="font-semibold text-sm mb-4">Tambah Komponen Gaji</h3>
            <form method="POST" action="{{ route('staff-payrolls.items.store', $staffPayroll) }}"
                  class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf
                <div>
                    <label class="block text-xs text-mk-dim mb-1">Tipe *</label>
                    <select name="item_type" class="w-full border-mk-border rounded text-sm" required>
                        <option value="ALLOWANCE">Tunjangan</option>
                        <option value="OVERTIME">Lainnya</option>
                        <option value="DEDUCTION">Potongan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-mk-dim mb-1">Kode *</label>
                    <select name="item_code" class="w-full border-mk-border rounded text-sm" required>
                        @foreach($itemCodes as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-mk-dim mb-1">Keterangan *</label>
                    <input type="text" name="description" class="w-full border-mk-border rounded text-sm" required
                           placeholder="Contoh: Tunjangan transport Juni 2026">
                </div>
                <div>
                    <label class="block text-xs text-mk-dim mb-1">Nominal (Rp) *</label>
                    <input type="number" name="amount" min="1" class="w-full border-mk-border rounded text-sm" required>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 rounded text-sm font-bold btn-mk-primary">Tambah</button>
                </div>
            </form>
        </div>
        @endif
    </div>
</x-app-layout>
