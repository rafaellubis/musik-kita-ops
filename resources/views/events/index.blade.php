<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Event Studio</h2>
                <div class="text-xs text-mk-muted mt-0.5">Mini Concert & Ujian Grade</div>
            </div>
            @if(auth()->user()->hasRole('Owner'))
            <a href="{{ route('events.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary"
               >
                + Buat Event
            </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">

        @if(session('success'))
        <div class="mb-5 p-3 rounded-lg text-sm"
             style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="mb-5 p-3 rounded-lg text-sm"
             style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
            {{ session('error') }}
        </div>
        @endif

        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-mk-surface">
                    <tr class="border-b text-xs text-mk-dim uppercase text-left">
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Nama Event</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-center">Peserta</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                        <tr class="border-b hover:bg-mk-surface">
                            <td class="px-4 py-3 font-mono text-xs text-mk-dim">{{ $event->event_number }}</td>
                            <td class="px-4 py-3 font-medium">
                                <a href="{{ route('events.show', $event) }}" class="text-blue-600 hover:underline">
                                    {{ $event->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-mk-muted text-xs">{{ $event->type_label }}</td>
                            <td class="px-4 py-3 text-mk-muted">{{ $event->event_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-center text-mk-muted">{{ $event->participants_count }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($event->status === 'DRAFT')
                                    <span class="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700">Draft</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Selesai</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('events.show', $event) }}" class="text-xs text-blue-600 hover:underline">Detail</a>
                                @if(auth()->user()->hasRole('Owner') && $event->isDraft())
                                    ·
                                    <a href="{{ route('events.edit', $event) }}" class="text-xs text-mk-muted hover:underline">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-mk-dim">
                                Belum ada event. Klik "+ Buat Event" untuk mulai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $events->links() }}</div>

    </div>
</x-app-layout>
