<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Katalog Item Tagihan Manual</h2>
            @role('Owner')
                <a href="{{ route('invoice-components.create') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">+ Tambah Item</a>
            @endrole
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500 mb-4">
                    Item di sini bisa dipilih Admin/Owner saat menambahkan tagihan manual ke invoice murid.
                    Hanya <strong>Owner</strong> yang bisa tambah/ubah/hapus katalog ini.
                </p>

                @if($components->isEmpty())
                    <p class="text-gray-500 text-sm">Belum ada item. Klik "+ Tambah Item" untuk mulai.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs text-gray-500 uppercase">
                                <th class="py-2">Urut</th>
                                <th class="py-2">Kode</th>
                                <th class="py-2">Nama Tampilan</th>
                                <th class="py-2 text-right">Harga Default</th>
                                <th class="py-2">Keterangan</th>
                                <th class="py-2 text-center">Status</th>
                                @role('Owner')
                                    <th class="py-2 text-right">Aksi</th>
                                @endrole
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($components as $c)
                                <tr class="{{ $c->is_active ? '' : 'opacity-50' }}">
                                    <td class="py-2 text-gray-400 text-xs">{{ $c->sort_order }}</td>
                                    <td class="py-2 font-mono font-bold">{{ $c->code }}</td>
                                    <td class="py-2">{{ $c->name }}</td>
                                    <td class="py-2 text-right font-medium text-orange-700">
                                        {{ $c->formatted_price }}
                                    </td>
                                    <td class="py-2 text-xs text-gray-500 max-w-xs">
                                        {{ $c->description ?? '—' }}
                                    </td>
                                    <td class="py-2 text-center">
                                        @if($c->is_active)
                                            <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                        @else
                                            <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-500">Nonaktif</span>
                                        @endif
                                    </td>
                                    @role('Owner')
                                        <td class="py-2 text-right whitespace-nowrap">
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
    </div>
</x-app-layout>
