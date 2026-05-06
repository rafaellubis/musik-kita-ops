<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Master Data — Instrumen
        </h2>
    </x-slot>
 
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
 
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif
 
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
 
                <div class="mb-4 flex justify-between items-center">
                    <h3 class="text-lg font-bold">
                        Daftar Instrumen ({{ $instruments->count() }})
                    </h3>
                    <a href="{{ route('instruments.create') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                        + Tambah Instrumen
                    </a>
                </div>
 
                <table class="min-w-full divide-y divide-gray-200 border">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-bold uppercase">Code</th>
                            <th class="px-4 py-2 text-left text-xs font-bold uppercase">Nama</th>
                            <th class="px-4 py-2 text-left text-xs font-bold uppercase">Deskripsi</th>
                            <th class="px-4 py-2 text-center text-xs font-bold uppercase">Status</th>
                            <th class="px-4 py-2 text-center text-xs font-bold uppercase">Urutan</th>
                            <th class="px-4 py-2 text-center text-xs font-bold uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($instruments as $instr)
                            <tr class="{{ $instr->is_active ? '' : 'bg-gray-50 opacity-60' }}">
                                <td class="px-4 py-2 text-sm font-mono font-bold">{{ $instr->code }}</td>
                                <td class="px-4 py-2 text-sm">{{ $instr->name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ $instr->description }}</td>
                                <td class="px-4 py-2 text-sm text-center">
                                    @if($instr->is_active)
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Aktif</span>
                                    @else
                                        <span class="px-2 py-1 text-xs bg-gray-200 text-gray-600 rounded">Non-aktif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-center">{{ $instr->sort_order }}</td>
                                <td class="px-4 py-2 text-sm text-center space-x-1">
                                    <a href="{{ route('instruments.edit', $instr) }}"
                                       class="text-blue-600 hover:underline">Edit</a>
                                    <form action="{{ route('instruments.toggle-active', $instr) }}"
                                          method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-amber-600 hover:underline">
                                            {{ $instr->is_active ? 'Non-aktif' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('instruments.destroy', $instr) }}"
                                          method="POST" class="inline"
                                          onsubmit="return confirm('Yakin hapus {{ $instr->name }}?');">
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
        </div>
    </div>
</x-app-layout>
