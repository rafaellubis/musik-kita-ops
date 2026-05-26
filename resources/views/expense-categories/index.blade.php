<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Kategori Pengeluaran</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $categories->count() }} kategori terdaftar</div>
            </div>
            @role('Owner|Admin')
            <a href="{{ route('expense-categories.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary"
               >
                + Tambah Kategori
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
        @if(session('error'))
        <div class="mb-5 p-3 rounded-lg text-sm"
             style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
            {{ session('error') }}
        </div>
        @endif

        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-mk-surface">
                    <tr class="border-b text-xs text-mk-dim uppercase text-left">
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
                        <tr class="border-b hover:bg-mk-surface {{ !$cat->is_active ? 'opacity-50' : '' }}">
                            <td class="px-4 py-3 font-mono text-xs">{{ $cat->code }}</td>
                            <td class="px-4 py-3 font-medium">{{ $cat->name }}</td>
                            <td class="px-4 py-3 text-mk-dim text-xs">{{ $cat->description ?? '—' }}</td>
                            <td class="px-4 py-3 text-center text-xs">{{ $cat->sort_order }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($cat->is_active)
                                    <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Aktif</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-xs bg-mk-surface text-mk-dim">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('expense-categories.edit', $cat) }}"
                                   class="text-xs text-blue-600 hover:underline">Edit</a>
                                @role('Owner')
                                ·
                                <form method="POST" action="{{ route('expense-categories.destroy', $cat) }}"
                                      class="inline"
                                      onsubmit="return confirm('Hapus kategori {{ $cat->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                                </form>
                                @endrole
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-mk-dim">
                                Belum ada kategori.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-app-layout>
