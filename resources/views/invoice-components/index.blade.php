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

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if($components->isEmpty())
                <div class="p-8 text-center text-gray-500">
                    Belum ada item. Klik "+ Tambah Item" untuk mulai.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="border-b text-left text-xs text-gray-500 uppercase">
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
                    <tbody class="divide-y divide-gray-100">
                        @foreach($components as $c)
                            <tr class="hover:bg-gray-50 {{ $c->is_active ? '' : 'opacity-50' }}">
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $c->sort_order }}</td>
                                <td class="px-4 py-2 font-mono font-bold">{{ $c->code }}</td>
                                <td class="px-4 py-2">{{ $c->name }}</td>
                                <td class="px-4 py-2 text-right font-medium text-orange-700">
                                    {{ $c->formatted_price }}
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500 max-w-xs">
                                    {{ $c->description ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    @if($c->is_active)
                                        <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                    @else
                                        <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-500">Nonaktif</span>
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
