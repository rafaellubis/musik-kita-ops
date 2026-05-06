<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Komponen Tagihan Invoice</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">

            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Total: {{ $components->count() }} komponen</h3>
                <a href="{{ route('invoice-components.create') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">+ Tambah</a>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Urut</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Code</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Nama</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Tipe</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Jumlah / Rumus</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Penjelasan</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Status</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @php
                        $typeBadge = [
                            'REGULER' => 'bg-blue-100 text-blue-800',
                            'TRIAL' => 'bg-purple-100 text-purple-800',
                            'KIDS_FINAL' => 'bg-pink-100 text-pink-800',
                            'CUTI' => 'bg-amber-100 text-amber-800',
                            'UJIAN' => 'bg-green-100 text-green-800',
                            'MINI_CONCERT' => 'bg-emerald-100 text-emerald-800',
                            'DENDA' => 'bg-red-100 text-red-800',
                        ];
                    @endphp
                    @foreach($components as $c)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-500">{{ $c->sort_order }}</td>
                            <td class="px-4 py-2 font-mono text-sm font-bold">{{ $c->code }}</td>
                            <td class="px-4 py-2 text-sm">{{ $c->name }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs {{ $typeBadge[$c->type] ?? 'bg-gray-100' }}">
                                    {{ $c->type }}
                                </span>
                            </td>
                            <td class="px-4 py-2 font-mono text-sm text-orange-700 bg-orange-50">
                                {{ $c->amount_or_formula }}
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-600 max-w-md">{{ $c->description }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($c->is_active)
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600">Off</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('invoice-components.edit', $c->id) }}"
                                   class="text-blue-600 hover:underline">Edit</a>
                                <form action="{{ route('invoice-components.destroy', $c->id) }}"
                                      method="POST" class="inline ml-2"
                                      onsubmit="return confirm('Hapus komponen {{ $c->code }}?');">
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