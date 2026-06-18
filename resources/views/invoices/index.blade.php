<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-mk-text">Tagihan (Invoice)</h2>
            @php $monthName = \Carbon\Carbon::create($year, $month, 1)->format('F Y'); @endphp
            <div class="text-xs text-mk-muted mt-0.5">{{ $monthName }}</div>
        </div>
    </x-slot>

    @php
        $statusStatsConfig = [
            'UNPAID'  => [
                'class' => 'bg-white border border-rose-200/80 text-rose-800 shadow-sm',
                'label' => 'Belum Lunas',
                'badge' => 'bg-rose-50 border border-rose-200 text-rose-800',
            ],
            'PARTIAL' => [
                'class' => 'bg-white border border-amber-200/80 text-amber-800 shadow-sm',
                'label' => 'Cicilan',
                'badge' => 'bg-amber-50 border border-amber-200 text-amber-800',
            ],
            'PAID'    => [
                'class' => 'bg-white border border-emerald-200/80 text-emerald-800 shadow-sm',
                'label' => 'Lunas',
                'badge' => 'bg-emerald-50 border border-emerald-200 text-emerald-800',
            ],
            'VOID'    => [
                'class' => 'bg-white border border-stone-200/80 text-stone-600 shadow-sm',
                'label' => 'Dibatalkan (Void)',
                'badge' => 'bg-stone-50 border border-stone-200 text-stone-600',
            ],
        ];
        $canManage = auth()->user()?->hasAnyRole(['Owner', 'Admin']);
    @endphp

    <div class="py-6 px-4 lg:px-8 space-y-4">

        {{-- ===== STATS PER STATUS + AGING ===== --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            @foreach(['UNPAID', 'PARTIAL', 'PAID', 'VOID'] as $st)
                @php
                    $row = $stats[$st] ?? null;
                    $cnt = $row?->cnt ?? 0;
                    $sum = $row?->sum_total ?? 0;
                    $cfg = $statusStatsConfig[$st];
                @endphp
                <div class="p-4 rounded-xl {{ $cfg['class'] }} flex flex-col justify-between transition-all hover:scale-[1.01]">
                    <div>
                        <div class="text-[10px] uppercase font-bold tracking-wider opacity-85">{{ $cfg['label'] }}</div>
                        <div class="text-3xl font-extrabold tracking-tight mt-1">{{ $cnt }}</div>
                    </div>
                    @if(auth()->user()?->hasRole('Owner'))
                        <div class="text-xs font-medium mt-2 pt-1 border-t border-current/10 opacity-90">
                            Rp {{ number_format($sum, 0, ',', '.') }}
                        </div>
                    @endif
                </div>
            @endforeach
            <div class="p-4 rounded-xl bg-white border border-orange-200/80 text-orange-800 shadow-sm flex flex-col justify-between transition-all hover:scale-[1.01]">
                <div>
                    <div class="text-[10px] uppercase font-bold tracking-wider opacity-85">Overdue</div>
                    <div class="text-3xl font-extrabold tracking-tight mt-1">{{ $overdueCount }}</div>
                </div>
                <div class="text-xs font-medium mt-2 pt-1 border-t border-orange-200/30 opacity-90">
                    Tagihan lewat tempo
                </div>
            </div>
        </div>

        <div class="bg-mk-card border border-mk-borderLight shadow-sm rounded-2xl p-6"
             x-data="{ tool: null }">

            <div class="flex justify-between items-center mb-5">
                <h3 class="text-sm font-semibold text-mk-muted">Daftar Tagihan — {{ $monthName }}</h3>
                @if($canManage)
                    <div class="flex gap-2">
                        <button type="button" @click="tool = tool === 'spp' ? null : 'spp'"
                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all btn-mk-primary focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-sidebar shadow-sm"
                                >
                            Generate SPP
                        </button>
                        <button type="button" @click="tool = tool === 'fines' ? null : 'fines'"
                                class="px-3.5 py-1.5 bg-amber-700 hover:bg-amber-800 text-white rounded-lg text-xs font-bold transition-all focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-amber-700 shadow-sm">
                            Apply Denda
                        </button>
                    </div>
                @endif
            </div>

            @if($canManage)
                {{-- ===== Form Generate SPP ===== --}}
                <div x-show="tool === 'spp'" x-cloak x-transition.duration.200ms
                     class="mb-4 p-4 border border-mk-border bg-mk-surface rounded-xl">
                    <form method="POST" action="{{ route('invoices.generate-spp') }}"
                          onsubmit="return confirm('Generate invoice SPP untuk bulan terpilih? Idempotent — invoice yang sudah ada tidak duplikat.')">
                        @csrf
                        <h4 class="font-semibold text-sm mb-1 text-mk-text">Generate SPP Bulanan</h4>
                        <p class="text-xs text-mk-dim mb-3">
                            Terbitkan invoice SPP untuk semua murid Aktif yang belum punya tagihan SPP di bulan target.
                            Jadwal otomatis: tanggal 1 setiap bulan (BR-5.1).
                        </p>
                        <div class="flex items-end gap-3">
                            <div>
                                <label class="block text-xs text-mk-dim mb-1 font-medium">Tahun</label>
                                <input type="number" name="year" required min="2024" max="2030"
                                       value="{{ now()->addMonth()->year }}"
                                       class="border-mk-border rounded-lg px-3 py-1.5 text-sm w-24 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                            </div>
                            <div>
                                <label class="block text-xs text-mk-dim mb-1 font-medium">Bulan</label>
                                <input type="number" name="month" required min="1" max="12"
                                       value="{{ now()->addMonth()->month }}"
                                       class="border-mk-border rounded-lg px-3 py-1.5 text-sm w-20 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                            </div>
                            <button type="submit"
                                    class="px-4 py-2 rounded-lg text-sm font-bold transition-all btn-mk-primary focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-sidebar"
                                    >
                                Jalankan Generator
                            </button>
                        </div>
                    </form>
                </div>

                {{-- ===== Form Apply Denda ===== --}}
                <div x-show="tool === 'fines'" x-cloak x-transition.duration.200ms
                     class="mb-4 p-4 border border-mk-warningBorder bg-mk-warningBg rounded-xl">
                    <form method="POST" action="{{ route('invoices.apply-fines') }}"
                          onsubmit="return confirm('Apply denda untuk invoice yang masih unpaid di bulan terpilih?')">
                        @csrf
                        <h4 class="font-semibold text-sm mb-1 text-mk-warning">Apply Denda Harian</h4>
                        <p class="text-xs text-mk-dim mb-3">
                            Tambah/update item DENDA Rp 5.000/hari untuk invoice UNPAID/PARTIAL.
                            Hari telat dihitung dari tanggal 11 sampai hari ini (BR-5.3). Jadwal otomatis: harian.
                        </p>
                        <div class="flex items-end gap-3">
                            <div>
                                <label class="block text-xs text-mk-dim mb-1 font-medium">Tahun</label>
                                <input type="number" name="year" required min="2024" max="2030"
                                       value="{{ $year }}"
                                       class="border-mk-border rounded-lg px-3 py-1.5 text-sm w-24 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                            </div>
                            <div>
                                <label class="block text-xs text-mk-dim mb-1 font-medium">Bulan</label>
                                <input type="number" name="month" required min="1" max="12"
                                       value="{{ $month }}"
                                       class="border-mk-border rounded-lg px-3 py-1.5 text-sm w-20 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                            </div>
                            <button type="submit"
                                    class="px-4 py-2 bg-amber-700 hover:bg-amber-800 text-white rounded-lg text-sm font-bold transition-all focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-amber-700">
                                Apply Denda
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- ===== FILTER ===== --}}
            <form method="GET" action="{{ route('invoices.index') }}" class="mb-5">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
                    <input type="number" name="year" value="{{ $year }}" min="2024" max="2030"
                           class="border-mk-border rounded-lg px-3 py-2 text-sm w-full focus:border-mk-accent focus:ring-1 focus:ring-mk-accent" placeholder="Tahun">
                    <select name="month" class="border-mk-border rounded-lg px-3 py-2 text-sm w-full focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create(2026, $m, 1)->format('F') }}
                            </option>
                        @endfor
                    </select>
                    <select name="status" class="border-mk-border rounded-lg px-3 py-2 text-sm w-full focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                        <option value="">Semua Status</option>
                        @foreach(['UNPAID', 'PARTIAL', 'PAID', 'VOID'] as $st)
                            <option value="{{ $st }}" {{ request('status') == $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                    </select>
                    @php
                        $studentFilterOptions = $students->map(fn ($s) => [
                            'value' => $s->id,
                            'label' => $s->student_code.' - '.$s->full_name,
                        ])->values()->all();
                    @endphp
                    <x-searchable-select
                        name="student_id"
                        placeholder="Semua Murid"
                        :selected="request('student_id')"
                        :options="$studentFilterOptions"
                    />
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="border-mk-border rounded-lg px-3 py-2 text-sm w-full focus:border-mk-accent focus:ring-1 focus:ring-mk-accent"
                           placeholder="No. invoice / nama">
                </div>
                <div class="mt-3 flex gap-2">
                    <button type="submit"
                            class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all btn-mk-primary focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-sidebar shadow-sm"
                            >
                        Filter
                    </button>
                    <a href="{{ route('invoices.index') }}"
                       class="px-4 py-1.5 mk-reset-btn rounded-lg text-xs font-bold hover:bg-opacity-80 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-muted shadow-sm">
                        Reset
                    </a>
                </div>
            </form>

            {{-- ===== TABLE ===== --}}
            <div class="overflow-x-auto rounded-xl border border-mk-borderLight">
                <table class="min-w-full text-sm divide-y divide-mk-borderLight">
                    <thead>
                        <tr class="bg-mk-surface border-b border-mk-borderLight text-left text-[10px] font-semibold uppercase tracking-widest text-mk-dim">
                            <th class="px-4 py-3">No. Invoice</th>
                            <th class="px-4 py-3">Murid</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3">Jatuh Tempo</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mk-borderLight">
                        @forelse($invoices as $inv)
                            <tr class="hover:bg-mk-surfaceHover transition-colors duration-150">
                                <td class="px-4 py-3 font-mono text-xs text-mk-text">{{ $inv->invoice_number }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('students.show', $inv->student_id) }}"
                                       class="text-mk-muted hover:text-mk-accent hover:underline transition-colors font-medium">
                                        {{ $inv->student->full_name ?? '?' }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    @foreach($inv->items as $item)
                                        <span class="inline-block px-1.5 py-0.5 bg-mk-surface border border-mk-borderLight text-mk-muted rounded text-[10px] font-semibold tracking-wider mr-1">{{ $item->item_code }}</span>
                                    @endforeach
                                    {{-- Badge khusus KIDS_CLASS_BUNDLE --}}
                                    @if($inv->class_type === 'KIDS_CLASS_BUNDLE')
                                        @if($inv->payment_mode === 'INSTALLMENT' && $inv->installment_number)
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold ml-1
                                                         bg-purple-50 border border-purple-200 text-purple-800">Kids Bundle</span>
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold ml-1
                                                         bg-sky-50 border border-sky-200 text-sky-800">
                                                Termin {{ $inv->installment_number }}/3
                                            </span>
                                        @elseif($inv->payment_mode === 'FULL')
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold ml-1
                                                         bg-purple-50 border border-purple-200 text-purple-800">Kids Bundle – Lunas</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-mk-text">
                                    Rp {{ number_format($inv->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-sm
                                    {{ $inv->balance > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                                    Rp {{ number_format($inv->balance, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php $cfg = $statusStatsConfig[$inv->status] ?? null; @endphp
                                    @if($cfg)
                                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full {{ $cfg['badge'] }}">
                                            {{ $cfg['label'] }}
                                        </span>
                                    @else
                                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-stone-100 text-stone-700 border border-stone-200">
                                            {{ $inv->status }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-mk-text">
                                    <span class="font-medium">{{ $inv->due_date->format('d M Y') }}</span>
                                    @if($inv->balance > 0 && $inv->due_date->lt(now()))
                                        <div class="text-orange-700 font-semibold mt-0.5">
                                            Telat {{ (int) $inv->due_date->diffInDays(now()) }} hari
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('invoices.show', $inv->id) }}"
                                       class="text-mk-muted hover:text-mk-accent hover:underline text-xs font-bold transition-colors">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-mk-dim">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <svg class="w-8 h-8 opacity-40 text-mk-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span class="text-sm font-medium">Tidak ada tagihan untuk periode/filter ini.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">{{ $invoices->links() }}</div>
        </div>

    </div>

    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
