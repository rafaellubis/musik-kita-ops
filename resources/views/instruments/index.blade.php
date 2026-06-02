<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Master Data — Instrumen</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $instruments->count() }} instrumen terdaftar</div>
            </div>
            <a href="{{ route('instruments.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary"
               >
                + Tambah Instrumen
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">

        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-mk-border">
                <thead class="bg-mk-surface">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-bold uppercase">Code</th>
                        <th class="px-4 py-2 text-left text-xs font-bold uppercase">Nama</th>
                        <th class="px-4 py-2 text-left text-xs font-bold uppercase">Deskripsi</th>
                        <th class="px-4 py-2 text-center text-xs font-bold uppercase">Status</th>
                        <th class="px-4 py-2 text-center text-xs font-bold uppercase">Urutan</th>
                        <th class="px-4 py-2 text-center text-xs font-bold uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-mk-card divide-y divide-mk-border">
                    @foreach($instruments as $instr)
                        <tr class="{{ $instr->is_active ? '' : 'bg-mk-surface opacity-60' }}">
                            <td class="px-4 py-2 text-sm font-mono font-bold">{{ $instr->code }}</td>
                            <td class="px-4 py-2 text-sm">{{ $instr->name }}</td>
                            <td class="px-4 py-2 text-sm text-mk-muted">{{ $instr->description }}</td>
                            <td class="px-4 py-2 text-sm text-center">
                                @if($instr->is_active)
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Aktif</span>
                                @else
                                    <span class="px-2 py-1 text-xs bg-mk-surface text-mk-muted rounded">Non-aktif</span>
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
</x-app-layout>
