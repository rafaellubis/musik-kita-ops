<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Master Data — Karyawan</h2>
                <div class="text-xs text-mk-dim mt-0.5">
                    {{ $employees->where('is_active', true)->count() }} aktif
                    dari {{ $employees->count() }} total
                </div>
            </div>
            @role('Owner')
            <a href="{{ route('employees.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary">
                + Tambah Karyawan
            </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-mk-dim">
                    <tr>
                        <th class="px-4 py-3 text-left">Kode</th>
                        <th class="px-4 py-3 text-left">Nama</th>
                        <th class="px-4 py-3 text-left">Posisi</th>
                        <th class="px-4 py-3 text-right">Gaji Pokok</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        @role('Owner')
                        <th class="px-4 py-3 text-right">Aksi</th>
                        @endrole
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($employees as $emp)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $emp->employee_code }}</td>
                        <td class="px-4 py-3 font-medium">{{ $emp->full_name }}</td>
                        <td class="px-4 py-3 text-mk-muted">{{ $emp->position }}</td>
                        <td class="px-4 py-3 text-right font-mono">
                            Rp {{ number_format($emp->base_salary, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($emp->is_active)
                                <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Aktif</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Nonaktif</span>
                            @endif
                        </td>
                        @role('Owner')
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('employees.edit', $emp) }}"
                               class="text-indigo-600 hover:underline text-xs">Edit</a>
                        </td>
                        @endrole
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-mk-dim">
                            Belum ada data karyawan. @role('Owner')<a href="{{ route('employees.create') }}" class="text-indigo-600 underline">Tambah karyawan</a>@endrole
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
