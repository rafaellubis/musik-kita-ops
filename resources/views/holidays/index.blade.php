<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Hari Libur {{ $year }}</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">

            <div class="flex justify-between items-center mb-4">
                <form method="GET" class="flex items-center gap-2">
                    <label class="text-sm font-medium">Tahun:</label>
                    <select name="year" onchange="this.form.submit()"
                            class="border-gray-300 rounded-md text-sm">
                        @foreach($availableYears as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                        @if(!$availableYears->contains($year))
                            <option value="{{ $year }}" selected>{{ $year }}</option>
                        @endif
                    </select>
                    <span class="text-sm text-gray-600 ml-3">
                        Total: {{ $holidays->count() }} hari libur
                    </span>
                </form>

                <a href="{{ route('holidays.create') }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">+ Tambah</a>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Tanggal</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Nama</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Tipe</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Catatan</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Status</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @php
                        $typeBadge = [
                            'Nasional' => 'bg-red-100 text-red-800',
                            'Cuti Bersama' => 'bg-amber-100 text-amber-800',
                            'Internal' => 'bg-blue-100 text-blue-800',
                        ];
                        $dayName = ['Sun'=>'Min','Mon'=>'Sen','Tue'=>'Sel','Wed'=>'Rab','Thu'=>'Kam','Fri'=>'Jum','Sat'=>'Sab'];
                    @endphp
                    @forelse($holidays as $h)
                        <tr>
                            <td class="px-4 py-2 text-sm">
                                <span class="font-mono">{{ $h->date->format('d M Y') }}</span>
                                <span class="ml-2 text-xs text-gray-500">
                                    ({{ $dayName[$h->date->format('D')] ?? $h->date->format('D') }})
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm">{{ $h->name }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs {{ $typeBadge[$h->type] ?? 'bg-gray-100' }}">
                                    {{ $h->type }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">{{ $h->notes ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($h->is_active)
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600">Off</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('holidays.edit', $h->id) }}"
                                   class="text-blue-600 hover:underline">Edit</a>
                                <form action="{{ route('holidays.destroy', $h->id) }}"
                                      method="POST" class="inline ml-2"
                                      onsubmit="return confirm('Hapus hari libur {{ $h->date->format('d M Y') }} ({{ $h->name }})?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                Belum ada hari libur untuk tahun {{ $year }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

        </div>

    </div></div>
</x-app-layout>