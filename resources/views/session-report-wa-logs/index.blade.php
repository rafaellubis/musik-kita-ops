<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-mk-text">Log Laporan Sesi WA</h2>
            <div class="text-xs text-mk-muted mt-0.5">Riwayat pengiriman laporan sesi otomatis ke orang tua via Fonnte</div>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <form method="GET" class="mb-4 bg-mk-card shadow-sm sm:rounded-lg p-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-mk-dim mb-1">Cari murid / kode</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="w-full rounded border-gray-300 text-sm"
                       placeholder="Nama atau kode murid...">
            </div>
            <div>
                <label class="block text-xs text-mk-dim mb-1">Tanggal sesi</label>
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}"
                       class="rounded border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs text-mk-dim mb-1">Status</label>
                <select name="status" class="rounded border-gray-300 text-sm">
                    <option value="">Semua</option>
                    @foreach([\App\Models\SessionReportWaLog::STATUS_SUCCESS, \App\Models\SessionReportWaLog::STATUS_FAILED, \App\Models\SessionReportWaLog::STATUS_SKIPPED] as $st)
                        <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">Filter</button>
        </form>

        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            @if($logs->isEmpty())
                <div class="p-8 text-center text-mk-dim">Belum ada log pengiriman.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-mk-surface text-mk-muted text-xs uppercase">
                            <tr>
                                <th class="px-4 py-3 text-left">Tanggal Sesi</th>
                                <th class="px-4 py-3 text-left">Murid</th>
                                <th class="px-4 py-3 text-left">Guru</th>
                                <th class="px-4 py-3 text-left">Instrumen</th>
                                <th class="px-4 py-3 text-left">Nomor</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Waktu Kirim</th>
                                @hasanyrole('Owner|Admin')
                                <th class="px-4 py-3 text-left">Aksi</th>
                                @endhasanyrole
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-mk-border">
                            @foreach($logs as $log)
                                <tr class="hover:bg-mk-surface/50">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ $log->classSession?->session_date
                                            ? \Carbon\Carbon::parse($log->classSession->session_date)->locale('id')->translatedFormat('d M Y')
                                            : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-mk-text">{{ $log->student?->full_name ?? '—' }}</div>
                                        <div class="text-xs text-mk-muted">{{ $log->student?->student_code }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $log->classSession?->teacher?->name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $log->classSession?->enrollment?->package?->instrument?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $waService->maskPhone($log->phone) }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $badge = match($log->status) {
                                                \App\Models\SessionReportWaLog::STATUS_SUCCESS => 'bg-green-100 text-green-800',
                                                \App\Models\SessionReportWaLog::STATUS_FAILED => 'bg-red-100 text-red-800',
                                                default => 'bg-gray-100 text-gray-700',
                                            };
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $badge }}">
                                            {{ $log->status }}
                                            @if($log->is_update) <span class="ml-1 opacity-70">(update)</span> @endif
                                        </span>
                                        @if($log->error_message)
                                            <div class="text-xs text-red-600 mt-1 max-w-xs truncate" title="{{ $log->error_message }}">{{ $log->error_message }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-mk-muted">
                                        {{ $log->sent_at?->locale('id')->translatedFormat('d M Y H:i') }}
                                    </td>
                                    @hasanyrole('Owner|Admin')
                                    <td class="px-4 py-3">
                                        @if($log->status === \App\Models\SessionReportWaLog::STATUS_FAILED)
                                            <form method="POST" action="{{ route('session-report-wa-logs.resend', $log) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs text-mk-accent hover:underline">Kirim ulang</button>
                                            </form>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    @endhasanyrole
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-mk-border">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
