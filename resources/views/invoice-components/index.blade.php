<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Katalog Item Tagihan</h2>
                <div class="text-xs text-mk-muted mt-0.5">Item yang bisa dipilih saat menambah tagihan manual ke invoice murid</div>
            </div>
            @role('Owner')
            <a href="{{ route('invoice-components.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary"
               >
                + Tambah Item
            </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">

        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            @if($components->isEmpty())
                <div class="p-8 text-center text-mk-dim">
                    Belum ada item. Klik "+ Tambah Item" untuk mulai.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-mk-surface">
                        <tr class="border-b text-left text-xs text-mk-dim uppercase">
                            <th class="px-4 py-3">Urut</th>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Nama Tampilan</th>
                            <th class="px-4 py-3 text-right">Harga Default</th>
                            <th class="px-4 py-3">Keterangan</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            @role('Owner')
                                <th class="px-4 py-3 text-right">Aksi</th>
                            @endrole
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mk-border">
                        @foreach($components as $c)
                            <tr class="hover:bg-mk-surface {{ $c->is_active ? '' : 'opacity-50' }}">
                                <td class="px-4 py-2 text-mk-dim text-xs">{{ $c->sort_order }}</td>
                                <td class="px-4 py-2 font-mono font-bold">{{ $c->code }}</td>
                                <td class="px-4 py-2">{{ $c->name }}</td>
                                <td class="px-4 py-2 text-right font-medium text-orange-700">
                                    {{ $c->formatted_price }}
                                </td>
                                <td class="px-4 py-2 text-xs text-mk-dim max-w-xs">
                                    {{ $c->description ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    @if($c->is_active)
                                        <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                    @else
                                        <span class="px-2 py-1 rounded text-xs bg-mk-surface text-mk-dim">Nonaktif</span>
                                    @endif
                                </td>
                                @role('Owner')
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <a href="{{ route('invoice-components.edit', $c->id) }}"
                                           class="text-blue-600 hover:underline text-xs">Edit</a>
                                        <form action="{{ route('invoice-components.destroy', $c->id) }}"
                                              method="POST" class="inline ml-2"
                                              onsubmit="return confirm('Hapus item {{ $c->code }}? Tidak bisa dihapus kalau sudah dipakai di invoice.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline text-xs">Hapus</button>
                                        </form>
                                    </td>
                                @endrole
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>
</x-app-layout>
