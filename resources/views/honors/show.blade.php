<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">{{ $honor->slip_number }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $honor->teacher->name ?? '?' }} · {{ $monthName }}
                </div>
            </div>
            <a href="{{ route('honors.index', ['year' => $honor->year, 'month' => $honor->month]) }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    @php
        $statusColors = [
            'DRAFT'      => 'bg-gray-100 text-gray-500 border-gray-300',
            'CALCULATED' => 'bg-blue-100 text-blue-700 border-blue-300',
            'PAID'       => 'bg-green-100 text-green-700 border-green-300',
        ];
        $isOwner = auth()->user()?->hasRole('Owner');

        // Label honor_code yang ditampilkan ke user
        $honorLabels = [
            'H_REG'    => 'Sesi Reguler (Hadir)',
            'H_TRIAL'  => 'Trial (Murid Hadir)',
            'TRIAL_NS' => 'Trial No-show (Honor Nol)',
            'H_VIDEO'  => 'Izin Video Pengganti',
            'H_LIBUR'  => 'Libur Nasional',
            'H_HANGUS' => 'Hangus (Murid No-show)',
            'H_PENG'   => 'Guru Pengganti',
            'H_KIDS'   => 'Kids Class',
            'H_UJIAN'  => 'Pengawas Ujian',
        ];
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

        {{-- ============= HEADER SLIP ============= --}}
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6">
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-mono text-sm text-mk-dim">{{ $honor->slip_number }}</div>
                    <div class="text-2xl font-bold mt-1">{{ $honor->teacher->name }}</div>
                    <div class="text-mk-muted text-sm mt-1">
                        Honor {{ $monthName }}
                        @if($honor->teacher->instruments->isNotEmpty())
                            · {{ $honor->teacher->instruments->pluck('name')->implode(', ') }}
                        @endif
                    </div>
                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        <span class="px-3 py-1 rounded text-sm border {{ $statusColors[$honor->status] }}">
                            {{ $honor->status_label }}
                        </span>
                        @if($honor->status === 'PAID' && $honor->paid_at)
                            <span class="text-sm text-mk-dim">
                                Dibayar {{ $honor->paid_at->format('d M Y') }}
                                @if($honor->paidBy) oleh {{ $honor->paidBy->name }} @endif
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2 flex-wrap justify-end">
                    <a href="{{ route('honors.print', $honor) }}" target="_blank"
                       class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                        Cetak Slip
                    </a>
                    @if($isOwner && !$honor->isLocked())
                        <a href="{{ route('honors.edit', $honor) }}"
                           class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                            Edit Komponen
                        </a>
                    @endif
                    @if($isOwner && $honor->status === 'CALCULATED')
                        <form method="POST" action="{{ route('honors.mark-paid', $honor) }}"
                              onsubmit="return confirm('Tandai slip {{ $honor->slip_number }} sebagai DIBAYAR? Setelah ini slip tidak bisa diubah lagi.')">
                            @csrf
                            <button type="submit"
                                    class="px-4 py-2 rounded text-sm font-bold"
                                    style="background:#16A34A;color:#fff">
                                Tandai Dibayar
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Ringkasan komponen honor --}}
            <div class="mt-5 border-t pt-4">
                <h3 class="text-sm font-medium text-mk-muted mb-3">Komponen Honor</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div class="bg-mk-surface rounded p-3">
                        <div class="text-xs text-mk-dim">Honor Pokok (Otomatis)</div>
                        <div class="text-lg font-bold mt-1">
                            Rp {{ number_format($honor->base_honor, 0, ',', '.') }}
                        </div>
                        <div class="text-xs text-mk-dim mt-1">dari {{ $sessions->count() }} sesi</div>
                    </div>
                    @if($honor->hasEventHonor())
                    <div class="bg-mk-surface rounded p-3">
                        <div class="text-xs text-mk-dim">Honor Event (Manual)</div>
                        <div class="text-lg font-bold mt-1">
                            Rp {{ number_format($honor->event_honor, 0, ',', '.') }}
                        </div>
                        @if($honor->event_honor_note)
                            <div class="text-xs text-mk-dim mt-1 italic">
                                {{ $honor->event_honor_note }}
                            </div>
                        @endif
                    </div>
                    @endif
                    <div class="bg-mk-surface rounded p-3">
                        <div class="text-xs text-mk-dim">Transport (Manual)</div>
                        <div class="text-lg font-bold mt-1">
                            Rp {{ number_format($honor->transport_honor, 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="bg-mk-surface rounded p-3">
                        <div class="text-xs text-mk-dim">Lain-lain (Manual)</div>
                        <div class="text-lg font-bold mt-1">
                            Rp {{ number_format($honor->other_honor, 0, ',', '.') }}
                        </div>
                        @if($honor->other_honor_note)
                            <div class="text-xs text-mk-dim mt-1 italic">
                                {{ $honor->other_honor_note }}
                            </div>
                        @endif
                    </div>
                    <div class="bg-blue-50 rounded p-3 border border-blue-200">
                        <div class="text-xs text-blue-600">Total Honor</div>
                        <div class="text-xl font-bold mt-1 text-blue-700">
                            Rp {{ number_format($honor->total_honor, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                @if($honor->teacher->bank_name || $honor->teacher->bank_account)
                    <div class="mt-3 text-sm text-mk-muted">
                        Transfer ke:
                        <span class="font-medium">{{ $honor->teacher->bank_name }}</span>
                        <span class="font-mono ml-1">{{ $honor->teacher->bank_account }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ============= RINCIAN SESI PER KATEGORI ============= --}}
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-mk-muted mb-4">Rincian Honor per Kategori</h3>

            @if($breakdown->isEmpty())
                <p class="text-sm text-mk-dim">Belum ada sesi yang dihitung untuk bulan ini.</p>
            @else
                {{-- Tabel ringkasan per kode --}}
                <div class="overflow-x-auto mb-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-mk-dim uppercase text-left">
                                <th class="py-1.5">Kode</th>
                                <th class="py-1.5">Keterangan</th>
                                <th class="py-1.5 text-right">Jumlah Sesi</th>
                                <th class="py-1.5 text-right">Total Honor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($breakdown as $code => $row)
                                <tr class="border-b">
                                    <td class="py-2 font-mono text-xs text-mk-muted">{{ $code }}</td>
                                    <td class="py-2">{{ $honorLabels[$code] ?? $code }}</td>
                                    <td class="py-2 text-right">{{ $row['count'] }}</td>
                                    <td class="py-2 text-right font-medium">
                                        Rp {{ number_format($row['total'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="font-bold border-t-2">
                                <td colspan="2" class="py-2 text-mk-muted">Total</td>
                                <td class="py-2 text-right">{{ $sessions->count() }}</td>
                                <td class="py-2 text-right">
                                    Rp {{ number_format($sessions->sum('honor_amount'), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Detail sesi (collapsible per murid) --}}
                <details>
                    <summary class="cursor-pointer text-sm text-blue-600 hover:underline mb-3">
                        Lihat detail {{ $sessions->count() }} sesi →
                    </summary>
                    <div class="overflow-x-auto mt-2">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b text-mk-dim uppercase text-left">
                                    <th class="py-1.5">Tanggal</th>
                                    <th class="py-1.5">Murid</th>
                                    <th class="py-1.5">Status</th>
                                    <th class="py-1.5">Kode</th>
                                    <th class="py-1.5 text-right">Honor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sessions as $sesi)
                                    <tr class="border-b hover:bg-mk-surface">
                                        <td class="py-1.5">{{ \Carbon\Carbon::parse($sesi->session_date)->format('d M') }}</td>
                                        <td class="py-1.5">
                                            {{ $sesi->student->full_name ?? '?' }}
                                            @if($sesi->substitute_teacher_id == $honor->teacher_id)
                                                <span class="text-orange-600">(pengganti)</span>
                                            @endif
                                        </td>
                                        <td class="py-1.5">{{ $sesi->status }}</td>
                                        <td class="py-1.5 font-mono">{{ $sesi->honor_code ?? '—' }}</td>
                                        <td class="py-1.5 text-right">
                                            Rp {{ number_format($sesi->honor_amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        </div>

        <p class="text-xs text-mk-dim text-right">
            * Sesi dihitung hingga H-2 sebelum akhir bulan
            ({{ \Carbon\Carbon::create($honor->year, $honor->month, 1)->endOfMonth()->subDays(2)->format('d M Y') }}).
        </p>

    </div>
</x-app-layout>
