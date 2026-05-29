<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Template Laporan Progres</h2>
                <div class="text-xs text-mk-muted mt-0.5">Template checklist per instrumen · dikelola Owner</div>
            </div>
            @role('Owner')
            <a href="{{ route('report-templates.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary">
                + Tambah Template
            </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        @if(session('success'))
            <div class="mb-5 p-3 rounded-lg text-sm" style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-5 p-3 rounded-lg text-sm" style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if($templates->isEmpty())
                <div class="p-8 text-center text-gray-400">
                    Belum ada template. Klik "+ Tambah Template" untuk mulai.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="border-b text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3">Urut</th>
                            <th class="px-4 py-3">Instrumen</th>
                            <th class="px-4 py-3">Nama Template</th>
                            <th class="px-4 py-3 text-center">Seksi</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($templates as $t)
                        <tr class="hover:bg-gray-50 {{ $t->is_active ? '' : 'opacity-50' }}">
                            <td class="px-4 py-2 text-gray-400 text-xs">{{ $t->sort_order }}</td>
                            <td class="px-4 py-2 font-medium text-gray-700">{{ $t->instrument->name }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('report-templates.show', $t) }}" class="text-indigo-600 hover:underline font-medium">{{ $t->name }}</a>
                                @if($t->description)
                                    <div class="text-xs text-gray-400 mt-0.5">{{ $t->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center text-gray-500">{{ $t->sections->count() }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($t->is_active)
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-500">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('report-templates.show', $t) }}" class="text-gray-500 hover:underline text-xs mr-2">Detail</a>
                                @role('Owner')
                                <a href="{{ route('report-templates.edit', $t) }}" class="text-indigo-600 hover:underline text-xs mr-2">Edit</a>
                                <form action="{{ route('report-templates.destroy', $t) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Hapus template {{ $t->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:underline text-xs">Hapus</button>
                                </form>
                                @endrole
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
