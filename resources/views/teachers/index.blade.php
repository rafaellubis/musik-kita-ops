<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Master Data — Guru</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            @if (session('success'))
                <div class="mb-4 p-3 bg-green-50 text-green-700 rounded">{{ session('success') }}</div>
            @endif
            <div class="mb-4 flex justify-between">
                <h3 class="text-lg font-bold">Daftar Guru ({{ $teachers->count() }})</h3>
                <a href="{{ route('teachers.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded">+ Tambah</a>
            </div>
            <table class="min-w-full divide-y divide-gray-200 border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-bold uppercase">Kode</th>
                        <th class="px-3 py-2 text-left text-xs font-bold uppercase">Nama</th>
                        <th class="px-3 py-2 text-left text-xs font-bold uppercase">Email</th>
                        <th class="px-3 py-2 text-left text-xs font-bold uppercase">Instrumen</th>
                        <th class="px-3 py-2 text-center text-xs font-bold uppercase">Status</th>
                        <th class="px-3 py-2 text-center text-xs font-bold uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y">
                    @foreach($teachers as $t)
                        <tr>
                            <td class="px-3 py-2 text-sm font-mono font-bold">{{ $t->code }}</td>
                            <td class="px-3 py-2 text-sm font-bold">{{ $t->name }}</td>
                            <td class="px-3 py-2 text-sm text-gray-600">{{ $t->email ?? '—' }}</td>
                            <td class="px-3 py-2 text-sm">
                                @foreach($t->instruments as $i)
                                    <span class="inline-block px-2 py-0.5 text-xs rounded mr-1 mb-1
                                        {{ $i->pivot->is_primary ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $i->pivot->is_primary ? '★ ' : '' }}{{ $i->name }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="px-3 py-2 text-sm text-center">
                                @if($t->is_active)
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Aktif</span>
                                @else
                                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">Non-aktif</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-sm text-center whitespace-nowrap">
                                <a href="{{ route('teachers.edit', $t->id) }}" class="text-blue-600">Edit</a>
                                <form action="{{ route('teachers.destroy', $t->id) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Yakin hapus {{ $t->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 ml-2">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div></div>
</x-app-layout>
