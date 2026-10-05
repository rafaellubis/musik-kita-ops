<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-mk-text">Event Studio</h1>
                <p class="mt-1 text-sm text-mk-muted">Kelola Mini Concert dari persiapan sampai selesai.</p>
            </div>
            @hasanyrole('Owner|Admin')
                <a href="{{ route('events.create') }}"
                   class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-mk-accent focus:ring-offset-2">
                    Buat Event
                </a>
            @endhasanyrole
        </div>
    </x-slot>

    @php
        $statuses = [
            'DRAFT' => ['Draft', 'bg-amber-50 text-amber-900 border-amber-200', 'bg-amber-500'],
            'PUBLISHED' => ['Dipublikasikan', 'bg-emerald-50 text-emerald-900 border-emerald-200', 'bg-emerald-600'],
            'COMPLETED' => ['Selesai', 'bg-mk-accentDim text-mk-text border-mk-border', 'bg-mk-accent'],
            'CANCELLED' => ['Dibatalkan', 'bg-red-50 text-red-900 border-red-200', 'bg-red-600'],
        ];
    @endphp

    <div class="px-4 py-6 lg:px-8">
        <form method="GET" action="{{ route('events.index') }}"
              class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-mk-borderLight bg-mk-card p-4">
            <div>
                <label for="event-filter-year" class="block text-xs font-semibold text-mk-muted">Tahun</label>
                <select id="event-filter-year" name="year" class="mt-1 rounded-md text-sm">
                    <option value="">Semua tahun</option>
                    @foreach($years as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="event-filter-status" class="block text-xs font-semibold text-mk-muted">Status</label>
                <select id="event-filter-status" name="status" class="mt-1 rounded-md text-sm">
                    <option value="">Semua status</option>
                    @foreach($statuses as $value => $meta)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $meta[0] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="event-filter-type" class="block text-xs font-semibold text-mk-muted">Tipe</label>
                <select id="event-filter-type" name="type" class="mt-1 rounded-md text-sm">
                    <option value="">Semua tipe</option>
                    @foreach(\App\Models\Event::TYPE_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold">Terapkan Filter</button>
            @if(array_filter($filters))
                <a href="{{ route('events.index') }}" class="px-2 py-2 text-sm font-semibold text-mk-muted hover:text-mk-text">Reset</a>
            @endif
        </form>

        <div class="overflow-hidden rounded-lg border border-mk-borderLight bg-mk-card">
            <div class="overflow-x-auto">
                <table class="min-w-[1120px] w-full text-sm">
                    <thead class="bg-mk-surface text-left text-xs font-semibold text-mk-muted">
                        <tr class="border-b border-mk-borderLight">
                            <th class="px-4 py-3">Event</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Peserta</th>
                            <th class="px-4 py-3">Kesiapan guru</th>
                            <th class="px-4 py-3">Progres absensi</th>
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mk-borderLight">
                        @forelse($events as $event)
                            @php($status = $statuses[$event->status] ?? [$event->status, 'bg-mk-surface text-mk-text border-mk-border', 'bg-mk-muted'])
                            <tr class="transition-colors hover:bg-mk-surface">
                                <td class="px-4 py-3">
                                    <a href="{{ route('events.show', $event) }}"
                                       class="font-semibold text-mk-text underline-offset-4 hover:text-mk-muted hover:underline focus:outline-none focus:ring-2 focus:ring-mk-accent">
                                        {{ $event->name }}
                                    </a>
                                    <div class="mt-0.5 font-mono text-xs text-mk-muted">{{ $event->event_number }}</div>
                                </td>
                                <td class="px-4 py-3 text-mk-text">{{ $event->type_label }}</td>
                                <td class="px-4 py-3 text-mk-text">{{ $event->event_date->translatedFormat('d M Y') }}</td>
                                <td class="px-4 py-3 tabular-nums text-mk-text">
                                    {{ $event->active_participants_count }} enrollment · {{ $event->unique_students_count }} murid
                                </td>
                                <td class="px-4 py-3 tabular-nums text-mk-text">
                                    {{ $event->ready_teachers_count }}/{{ $event->teachers_count }} guru siap
                                </td>
                                <td class="px-4 py-3 tabular-nums text-mk-text">
                                    {{ $event->recorded_attendance_count }}/{{ $event->active_participants_count }} absensi
                                </td>
                                <td class="px-4 py-3 tabular-nums text-mk-text">
                                    @if($event->active_invoices_count > 0)
                                        {{ $event->paid_invoices_count }}/{{ $event->active_invoices_count }} invoice lunas
                                    @else
                                        Belum ada invoice
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $status[1] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $status[2] }}" aria-hidden="true"></span>
                                        {{ $status[0] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('events.show', $event) }}"
                                       class="font-semibold text-mk-muted underline-offset-4 hover:text-mk-text hover:underline focus:outline-none focus:ring-2 focus:ring-mk-accent">
                                        Buka
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <p class="font-semibold text-mk-text">Belum ada event.</p>
                                    <p class="mt-1 text-sm text-mk-muted">Event yang dibuat akan tampil di sini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($events->hasPages())
            <div class="mt-4">{{ $events->links() }}</div>
        @endif
    </div>
</x-app-layout>
