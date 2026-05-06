<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Master Data — Paket</h2></x-slot>
 
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if (session('success'))
                    <div class="mb-4 p-3 bg-green-50 text-green-700 rounded">{{ session('success') }}</div>
                @endif
                <div class="mb-4 flex justify-between items-center">
                    <h3 class="text-lg font-bold">Daftar Paket ({{ $packages->count() }})</h3>
                    <a href="{{ route('packages.create') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">+ Tambah</a>
                </div>
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 border">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase">Code</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase">Instrumen</th>
                            <th class="px-3 py-2 text-left text-xs font-bold uppercase">Tipe</th>
                            <th class="px-3 py-2 text-center text-xs font-bold uppercase">Grade</th>
                            <th class="px-3 py-2 text-center text-xs font-bold uppercase">Durasi</th>
                            <th class="px-3 py-2 text-right text-xs font-bold uppercase">Harga/Bulan</th>
                            <th class="px-3 py-2 text-center text-xs font-bold uppercase">Status</th>
                            <th class="px-3 py-2 text-center text-xs font-bold uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y">
                        @foreach($packages as $pkg)
                            <tr>
                                <td class="px-3 py-2 text-xs font-mono font-bold">{{ $pkg->code }}</td>
                                <td class="px-3 py-2 text-sm">{{ $pkg->instrument->name }}</td>
                                <td class="px-3 py-2 text-sm">{{ $pkg->class_type }}</td>
                                <td class="px-3 py-2 text-sm text-center">{{ $pkg->grade ?? '—' }}</td>
                                <td class="px-3 py-2 text-sm text-center">{{ $pkg->duration_min }}'</td>
                                <td class="px-3 py-2 text-sm text-right">Rp {{ number_format($pkg->price_per_month, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-sm text-center">
                                    @if($pkg->is_active)
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Aktif</span>
                                    @else
                                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">Non-aktif</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-center whitespace-nowrap">
                                    <a href="{{ route('packages.edit', $pkg->id) }}" class="text-blue-600 hover:underline">Edit</a>
                                    <form action="{{ route('packages.destroy', $pkg->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Yakin hapus?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline ml-2">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
