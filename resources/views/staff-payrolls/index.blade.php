<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-mk-text">Slip Gaji Staff</h2>
            <div class="text-xs text-mk-muted mt-0.5">{{ $monthName }}</div>
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
        $totalAll  = $stats->sum('sum_total');
        $totalPaid = $stats->get('PAID')?->sum_total ?? 0;
        $totalCalc = $stats->get('CALCULATED')?->sum_total ?? 0;
        $cntPaid   = $stats->get('PAID')?->cnt ?? 0;
        $cntBelum  = ($stats->get('CALCULATED')?->cnt ?? 0) + ($stats->get('DRAFT')?->cnt ?? 0);
    @endphp

    <div class="py-6 px-4 lg:px-8 space-y-4">

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
                                {{ \Carbon\Carbon::create(null, $m, 1)->locale('id')->translatedFormat('M') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-mk-dim mb-1">Status</label>
                    <select name="status" class="border-mk-border rounded text-sm" onchange="this.form.submit()">
                        <option value="">Semua</option>
                        @foreach($statusLabels as $val => $label)
                            <option value="{{ $val }}" {{ request('status') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @if($isOwner)
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
                <div class="text-xs text-mk-dim">Total Gaji Bulan Ini</div>
                <div class="text-xl font-bold mt-1">Rp {{ number_format($totalAll, 0, ',', '.') }}</div>
            </div>
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
                <div class="text-xs text-mk-dim">Sudah Dibayarkan</div>
                <div class="text-xl font-bold mt-1 text-green-600">Rp {{ number_format($totalPaid, 0, ',', '.') }}</div>
                <div class="text-xs text-mk-dim">{{ $cntPaid }} slip</div>
            </div>
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
                <div class="text-xs text-mk-dim">Belum Dibayar</div>
                <div class="text-xl font-bold mt-1 text-blue-600">Rp {{ number_format($totalCalc, 0, ',', '.') }}</div>
                <div class="text-xs text-mk-dim">{{ $cntBelum }} slip</div>
            </div>
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
                <div class="text-xs text-mk-dim">Karyawan Belum Ada Slip</div>
                <div class="text-xl font-bold mt-1 {{ $missingCount > 0 ? 'text-orange-500' : 'text-mk-dim' }}">
                    {{ $missingCount }}
                </div>
            </div>
        </div>

        <div class="bg-mk-card shadow-sm sm:rounded-lg p-4 flex items-center gap-4 flex-wrap">
            <form method="POST" action="{{ route('staff-payrolls.generate') }}"
                  onsubmit="return confirm('Generate slip gaji {{ $monthName }} untuk semua karyawan aktif? Slip PAID tidak akan diubah.')">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">
                    Generate Slip {{ $monthName }}
                </button>
            </form>
            <p class="text-sm text-mk-dim">
                Buat/update slip untuk semua karyawan aktif. Slip PAID tidak diubah.
                @if($missingCount > 0)
                    <span class="text-orange-600 font-medium">{{ $missingCount }} karyawan belum punya slip.</span>
                @endif
            </p>
        </div>
        @endif

        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            @if($slips->isEmpty())
                <div class="p-8 text-center text-mk-dim">
                    <p>Belum ada slip gaji untuk {{ $monthName }}.</p>
                    @if($isOwner)
                        <p class="text-sm mt-1">Klik "Generate Slip" untuk membuat slip dari master karyawan.</p>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-mk-surface">
                            <tr class="border-b text-left text-xs text-mk-dim uppercase">
                                <th class="px-4 py-3">No. Slip</th>
                                <th class="px-4 py-3">Karyawan</th>
                                @if($isOwner)
                                <th class="px-4 py-3 text-right">Gaji Pokok</th>
                                <th class="px-4 py-3 text-right">Tunjangan</th>
                                <th class="px-4 py-3 text-right">Potongan</th>
                                <th class="px-4 py-3 text-right font-bold">Net</th>
                                @endif
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slips as $slip)
                            <tr class="border-b hover:bg-mk-surface">
                                <td class="px-4 py-3 font-mono text-xs">{{ $slip->slip_number }}</td>
                                <td class="px-4 py-3 font-medium">{{ $slip->employee->full_name ?? '?' }}</td>
                                @if($isOwner)
                                <td class="px-4 py-3 text-right">Rp {{ number_format($slip->base_salary, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">
                                    {{ $slip->total_allowances > 0 ? 'Rp ' . number_format($slip->total_allowances, 0, ',', '.') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-red-600">
                                    {{ $slip->total_deductions > 0 ? '(Rp ' . number_format($slip->total_deductions, 0, ',', '.') . ')' : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($slip->net_salary, 0, ',', '.') }}</td>
                                @endif
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs border {{ $statusColors[$slip->status] }}">
                                        {{ $statusLabels[$slip->status] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('staff-payrolls.show', $slip) }}" class="text-xs text-blue-600 hover:underline">Detail</a>
                                    ·
                                    <a href="{{ route('staff-payrolls.print', $slip) }}" target="_blank" class="text-xs text-mk-muted hover:underline">Cetak</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $slips->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
