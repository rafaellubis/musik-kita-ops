<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Kategori Pengeluaran</h2>
            <a href="{{ route('expense-categories.create') }}"
               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                + Tambah Kategori
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

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

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="border-b text-xs text-gray-500 uppercase text-left">
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Deskripsi</th>
                            <th class="px-4 py-3 text-center">Urutan</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                            <tr class="border-b hover:bg-gray-50 {{ !$cat->is_active ? 'opacity-50' : '' }}">
                                <td class="px-4 py-3 font-mono text-xs">{{ $cat->code }}</td>
                                <td class="px-4 py-3 font-medium">{{ $cat->name }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $cat->description ?? '—' }}</td>
                                <td class="px-4 py-3 text-center text-xs">{{ $cat->sort_order }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if($cat->is_active)
                                        <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Aktif</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('expense-categories.edit', $cat) }}"
                                       class="text-xs text-indigo-600 hover:underline">Edit</a>
                                    ·
                                    <form method="POST" action="{{ route('expense-categories.destroy', $cat) }}"
                                          class="inline"
                                          onsubmit="return confirm('Hapus kategori {{ $cat->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:underline">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Belum ada kategori.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
