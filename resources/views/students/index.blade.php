<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Daftar Murid</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

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

            {{-- ============= STATS CARDS ============= --}}
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
                @php
                    $statusList = ['Calon', 'Trial', 'Aktif', 'Cuti', 'Selesai', 'Mengundurkan Diri'];
                    $statusColors = [
                        'Calon' => 'bg-gray-100 text-gray-700',
                        'Trial' => 'bg-purple-100 text-purple-700',
                        'Aktif' => 'bg-green-100 text-green-700',
                        'Cuti' => 'bg-amber-100 text-amber-700',
                        'Selesai' => 'bg-blue-100 text-blue-700',
                        'Mengundurkan Diri' => 'bg-red-100 text-red-700',
                    ];
                @endphp
                @foreach($statusList as $st)
                    <div class="p-3 rounded {{ $statusColors[$st] }}">
                        <div class="text-xs uppercase">{{ $st }}</div>
                        <div class="text-2xl font-bold">{{ $stats[$st] ?? 0 }}</div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                {{-- ============= FILTER BAR ============= --}}
                <form method="GET" action="{{ route('students.index') }}" class="mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                        <input type="text" name="search"
                               value="{{ request('search') }}"
                               placeholder="Cari nama atau kode..."
                               class="border-gray-300 rounded text-sm">

                        <select name="status" class="border-gray-300 rounded text-sm">
                            <option value="">Semua Status</option>
                            @foreach($statusList as $st)
                                <option value="{{ $st }}"
                                    {{ request('status') == $st ? 'selected' : '' }}>{{ $st }}</option>
                            @endforeach
                        </select>

                        <select name="instrument_id" class="border-gray-300 rounded text-sm">
                            <option value="">Semua Instrumen</option>
                            @foreach($instruments as $inst)
                                <option value="{{ $inst->id }}"
                                    {{ request('instrument_id') == $inst->id ? 'selected' : '' }}>
                                    {{ $inst->name }}</option>
                            @endforeach
                        </select>

                        <select name="package_id" class="border-gray-300 rounded text-sm">
                            <option value="">Semua Paket</option>
                            @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}"
                                    {{ request('package_id') == $pkg->id ? 'selected' : '' }}>
                                    {{ $pkg->code }}</option>
                            @endforeach
                        </select>

                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Filter
                            </button>
                            <a href="{{ route('students.index') }}" class="px-4 py-2 bg-gray-200 rounded">
                                Reset
                            </a>
                        </div>
                    </div>
                </form>

                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">
                        Total: {{ $students->total() }} murid
                        ({{ $students->firstItem() ?? 0 }}-{{ $students->lastItem() ?? 0 }})
                    </h3>
                    <a href="{{ route('students.create') }}"
                       class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        + Tambah Murid
                    </a>
                </div>

                {{-- ============= TABLE ============= --}}
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase">Kode</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase">Nama</th>
                            <th class="px-3 py-2 text-center text-xs font-medium uppercase">L/P</th>
                            <th class="px-3 py-2 text-center text-xs font-medium uppercase">Umur</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase">Paket</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase">Guru</th>
                            <th class="px-3 py-2 text-center text-xs font-medium uppercase">Status</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($students as $s)
                            <tr>
                                <td class="px-3 py-2 font-mono text-sm">{{ $s->student_code }}</td>
                                <td class="px-3 py-2 text-sm">
                                    <div class="font-medium">{{ $s->full_name }}</div>
                                    @if($s->nickname)
                                        <div class="text-xs text-gray-500">({{ $s->nickname }})</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-sm text-center">{{ $s->gender }}</td>
                                <td class="px-3 py-2 text-sm text-center">{{ $s->age ?? '—' }}</td>
                                <td class="px-3 py-2 text-sm">
                                    {{-- TIDAK ADA $package->name di schema M01. Pakai code. --}}
                                    {{ $s->package?->code ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-sm">
                                    {{ $s->assignedTeacher?->name ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-1 rounded text-xs {{ $statusColors[$s->status] ?? 'bg-gray-100' }}">
                                        {{ $s->status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('students.show', $s->id) }}"
                                       class="text-blue-600 hover:underline text-sm">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-gray-500">
                                    Tidak ada murid yang sesuai filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- ============= PAGINATION ============= --}}
                <div class="mt-4">
                    {{ $students->links() }}
                </div>

            </div>
        </div>
    </div>
</x-app-layout>