<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Master Data — Paket</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $packages->count() }} paket terdaftar</div>
            </div>
            @role('Owner')
            <a href="{{ route('packages.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors"
               style="background:#D4A853;color:#1A1000">
                + Tambah Paket
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

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
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
                    <tbody class="divide-y divide-gray-100">
                        @foreach($packages as $pkg)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-xs font-mono font-bold">{{ $pkg->code }}</td>
                                <td class="px-3 py-2 text-sm">{{ $pkg->instrument->name }}</td>
                                <td class="px-3 py-2 text-sm">{{ $pkg->class_type }}</td>
                                <td class="px-3 py-2 text-sm text-center">{{ $pkg->grade ?? '—' }}</td>
                                <td class="px-3 py-2 text-sm text-center">{{ $pkg->duration_min }}'</td>
                                <td class="px-3 py-2 text-sm text-right">
                                    Rp {{ number_format($pkg->price_per_month, 0, ',', '.') }}
                                </td>
                                <td class="px-3 py-2 text-sm text-center">
                                    @if($pkg->is_active)
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Aktif</span>
                                    @else
                                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">Non-aktif</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-center whitespace-nowrap">
                                    @role('Owner')
                                    <a href="{{ route('packages.edit', $pkg->id) }}"
                                       class="text-blue-600 hover:underline">Edit</a>
                                    <form action="{{ route('packages.destroy', $pkg->id) }}"
                                          method="POST" class="inline ml-1"
                                          onsubmit="return confirm('Yakin hapus paket {{ addslashes($pkg->code) }}?')">
                                        @csrf @method('DELETE')
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

    </div>
</x-app-layout>
