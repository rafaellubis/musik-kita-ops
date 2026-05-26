<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-mk-text">Slip Honor Guru</h2>
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
    @endphp

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
                                {{ \Carbon\Carbon::create(null, $m, 1)->format('M') }} ({{ $m }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-mk-dim mb-1">Status</label>
                    <select name="status" class="border-mk-border rounded text-sm" onchange="this.form.submit()">
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

        {{-- ===== STATS ===== --}}
        @php
            $totalAll  = $stats->sum('sum_total');
            $totalPaid = $stats->get('PAID')?->sum_total ?? 0;
            $totalCalc = $stats->get('CALCULATED')?->sum_total ?? 0;
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
                <div class="text-xs text-mk-dim">Total Honor Bulan Ini</div>
                <div class="text-xl font-bold mt-1">Rp {{ number_format($totalAll, 0, ',', '.') }}</div>
            </div>
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
                <div class="text-xs text-mk-dim">Sudah Dibayarkan</div>
                <div class="text-xl font-bold mt-1 text-green-600">Rp {{ number_format($totalPaid, 0, ',', '.') }}</div>
                <div class="text-xs text-mk-dim">{{ $stats->get('PAID')?->cnt ?? 0 }} slip</div>
            </div>
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
                <div class="text-xs text-mk-dim">Belum Dibayar (Terhitung)</div>
                <div class="text-xl font-bold mt-1 text-blue-600">Rp {{ number_format($totalCalc, 0, ',', '.') }}</div>
                <div class="text-xs text-mk-dim">{{ $stats->get('CALCULATED')?->cnt ?? 0 }} slip</div>
            </div>
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
                <div class="text-xs text-mk-dim">Guru Belum Ada Slip</div>
                <div class="text-xl font-bold mt-1 {{ $missingCount > 0 ? 'text-orange-500' : 'text-mk-dim' }}">
                    {{ $missingCount }} guru
                </div>
            </div>
        </div>

        {{-- ===== KALKULASI HONOR (Owner) ===== --}}
        @if($isOwner)
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-4 flex items-center gap-4 flex-wrap">
            <form method="POST" action="{{ route('honors.calculate') }}"
                  onsubmit="return confirm('Kalkulasi honor {{ $monthName }} untuk semua guru aktif? Slip PAID tidak akan diubah.')">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary"
                        >
                    Hitung Honor {{ $monthName }}
                </button>
            </form>
            <p class="text-sm text-mk-dim">
                Generate/update slip untuk semua {{ \App\Models\Teacher::where('is_active', true)->count() }} guru aktif.
                Slip PAID tidak akan diubah.
                @if($missingCount > 0)
                    <span class="text-orange-600 font-medium">
                        {{ $missingCount }} guru belum punya slip bulan ini.
                    </span>
                @endif
            </p>
        </div>
        @endif

        {{-- ===== TABEL SLIP ===== --}}
        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            @if($slips->isEmpty())
                <div class="p-8 text-center text-mk-dim">
                    <p>Belum ada slip honor untuk {{ $monthName }}.</p>
                    @if($isOwner)
                        <p class="text-sm mt-1">Klik "Hitung Honor" di atas untuk membuat slip dari data absensi.</p>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-mk-surface">
                            <tr class="border-b text-left text-xs text-mk-dim uppercase">
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
                                <tr class="border-b hover:bg-mk-surface">
                                    <td class="px-4 py-3 font-mono text-xs text-mk-dim">{{ $slip->slip_number }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $slip->teacher->name ?? '?' }}</td>
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
                                           class="text-xs text-mk-muted hover:underline">Cetak</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-mk-surface">
                            <tr>
                                <td colspan="2" class="px-4 py-2 text-xs text-mk-dim">{{ $slips->total() }} slip</td>
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
                </div>
                <div class="p-4">{{ $slips->withQueryString()->links() }}</div>
            @endif
        </div>

    </div>
</x-app-layout>
