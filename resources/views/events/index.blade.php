<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Event Studio</h2>
            @if(auth()->user()->hasRole('Owner'))
            <a href="{{ route('events.create') }}"
               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                + Buat Event
            </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded">{{ session('error') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="border-b text-xs text-gray-500 uppercase text-left">
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
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $event->event_number }}</td>
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('events.show', $event) }}" class="text-indigo-600 hover:underline">
                                        {{ $event->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs">{{ $event->type_label }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $event->event_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $event->participants_count }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if($event->status === 'DRAFT')
                                        <span class="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700">Draft</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Selesai</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('events.show', $event) }}" class="text-xs text-indigo-600 hover:underline">Detail</a>
                                    @if(auth()->user()->hasRole('Owner') && $event->isDraft())
                                        ·
                                        <a href="{{ route('events.edit', $event) }}" class="text-xs text-gray-600 hover:underline">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    Belum ada event.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $events->links() }}</div>
        </div>
    </div>
</x-app-layout>
