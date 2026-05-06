<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Konfigurasi Honor Guru</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Total: {{ $configs->count() }} konfigurasi</h3>
                <a href="{{ route('payroll-configs.create') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">+ Tambah</a>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Code</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Nama</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Tipe</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Value / Rumus</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Status</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @php
                        $badge = [
                            'PERCENTAGE' => 'bg-blue-100 text-blue-800',
                            'PER_STUDENT' => 'bg-green-100 text-green-800',
                            'FIXED' => 'bg-amber-100 text-amber-800',
                            'CONSTANT' => 'bg-gray-100 text-gray-800',
                        ];
                    @endphp
                    @foreach($configs as $cfg)
                        <tr>
                            <td class="px-4 py-2 font-mono text-sm">{{ $cfg->scenario_code }}</td>
                            <td class="px-4 py-2 text-sm">{{ $cfg->scenario_name }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs {{ $badge[$cfg->formula_type] ?? 'bg-gray-100' }}">
                                    {{ $cfg->formula_type }}
                                </span>
                            </td>
                            <td class="px-4 py-2 font-mono text-sm text-orange-700 bg-orange-50">
                                {{ $cfg->value_or_formula }}
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($cfg->is_active)
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600">Off</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('payroll-configs.edit', $cfg->id) }}"
                                   class="text-blue-600 hover:underline">Edit</a>
                                <form action="{{ route('payroll-configs.destroy', $cfg->id) }}"
                                      method="POST" class="inline ml-2"
                                      onsubmit="return confirm('Hapus konfigurasi {{ $cfg->scenario_code }}?');">
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