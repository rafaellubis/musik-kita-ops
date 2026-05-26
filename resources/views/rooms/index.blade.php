<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Ruangan Studio</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $rooms->count() }} ruangan terdaftar</div>
            </div>
            @role('Owner|Admin')
            <a href="{{ route('rooms.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary"
               >
                + Tambah Ruangan
            </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">

        @if(session('success'))
        <div class="mb-5 p-3 rounded-lg text-sm"
             style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
            {{ session('success') }}
        </div>
        @endif

        @if(session('warning'))
        <div class="mb-5 p-3 rounded-lg text-sm"
             style="background:rgba(251,191,36,0.1);color:#FBBF24;border:1px solid rgba(251,191,36,0.2)">
            ⚠️ {{ session('warning') }}
        </div>
        @endif

        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-mk-border">
                <thead class="bg-mk-surface">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Code</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Nama</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Kapasitas</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Instrumen</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Catatan</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Status</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mk-border">
                    @foreach($rooms as $r)
                        <tr class="hover:bg-mk-surface">
                            <td class="px-4 py-2 font-mono text-sm font-bold">{{ $r->code }}</td>
                            <td class="px-4 py-2 text-sm">{{ $r->name }}</td>
                            <td class="px-4 py-2 text-sm text-center">{{ $r->capacity }} org</td>
                            <td class="px-4 py-2 text-xs text-mk-muted">
                                @php $instrumen = $r->supported_instruments ?? []; @endphp
                                @if(count($instrumen) > 0)
                                    {{ implode(', ', $instrumen) }}
                                @else
                                    <span class="text-mk-dim">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-mk-muted max-w-xs">{{ $r->notes ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($r->is_active)
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-mk-surface text-mk-muted">Off</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('rooms.edit', $r->id) }}"
                                   class="text-blue-600 hover:underline">Edit</a>
                                @role('Owner')
                                <form action="{{ route('rooms.destroy', $r->id) }}"
                                      method="POST" class="inline ml-2"
                                      onsubmit="return confirm('Hapus ruangan {{ $r->code }} ({{ $r->name }})?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                </form>
                                @endrole
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
</x-app-layout>
