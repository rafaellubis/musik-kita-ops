<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Ruangan Studio</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">

            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Total: {{ $rooms->count() }} ruangan</h3>
                <a href="{{ route('rooms.create') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">+ Tambah</a>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Code</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Nama</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Kapasitas</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Piano</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Drum</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Amplifier</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Catatan</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Status</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($rooms as $r)
                        <tr>
                            <td class="px-4 py-2 font-mono text-sm font-bold">{{ $r->code }}</td>
                            <td class="px-4 py-2 text-sm">{{ $r->name }}</td>
                            <td class="px-4 py-2 text-sm text-center">{{ $r->capacity }} org</td>
                            <td class="px-4 py-2 text-center">
                                @if($r->has_piano)
                                    <span class="text-green-600 font-bold">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($r->has_drum)
                                    <span class="text-green-600 font-bold">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($r->has_amplifier)
                                    <span class="text-green-600 font-bold">✓</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-600 max-w-xs">{{ $r->notes ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($r->is_active)
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600">Off</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('rooms.edit', $r->id) }}"
                                   class="text-blue-600 hover:underline">Edit</a>
                                <form action="{{ route('rooms.destroy', $r->id) }}"
                                      method="POST" class="inline ml-2"
                                      onsubmit="return confirm('Hapus ruangan {{ $r->code }} ({{ $r->name }})?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div></div>
</x-app-layout>