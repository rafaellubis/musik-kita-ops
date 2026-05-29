<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">{{ $reportTemplate->name }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $reportTemplate->instrument->name }} · {{ $reportTemplate->is_active ? 'Aktif' : 'Nonaktif' }}
                </div>
            </div>
            @role('Owner')
            <a href="{{ route('report-templates.edit', $reportTemplate) }}"
               class="px-4 py-2 rounded-lg text-sm font-bold border border-gray-200 text-gray-600 hover:bg-gray-50">
                Edit Info
            </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg text-sm" style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 rounded-lg text-sm" style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
                {{ session('error') }}
            </div>
        @endif

        @foreach($reportTemplate->sections as $section)
        <div class="bg-white shadow-sm rounded-lg mb-4 overflow-hidden">
            <div class="flex justify-between items-center px-5 py-3 border-b border-gray-100 bg-gray-50">
                <div class="font-semibold text-gray-700 text-sm">{{ $section->sort_order }}. {{ $section->title }}</div>
                @role('Owner')
                <form action="{{ route('report-templates.sections.destroy', [$reportTemplate, $section]) }}"
                      method="POST" onsubmit="return confirm('Hapus seksi dan semua indikatornya?')">
                    @csrf @method('DELETE')
                    <button class="text-red-400 hover:text-red-600 text-xs">Hapus Seksi</button>
                </form>
                @endrole
            </div>

            <div class="divide-y divide-gray-50">
                @forelse($section->items as $item)
                <div class="flex justify-between items-center px-5 py-2 text-sm text-gray-700">
                    <span>{{ $item->sort_order }}. {{ $item->label }}</span>
                    @role('Owner')
                    <form action="{{ route('report-templates.items.destroy', [$reportTemplate, $section, $item]) }}"
                          method="POST" onsubmit="return confirm('Hapus indikator ini?')">
                        @csrf @method('DELETE')
                        <button class="text-red-400 hover:text-red-600 text-xs">x</button>
                    </form>
                    @endrole
                </div>
                @empty
                <div class="px-5 py-3 text-xs text-gray-400">Belum ada indikator.</div>
                @endforelse
            </div>

            @role('Owner')
            <div class="border-t border-gray-100 px-5 py-3 bg-gray-50">
                <form method="POST" action="{{ route('report-templates.items.store', [$reportTemplate, $section]) }}" class="flex gap-2">
                    @csrf
                    <input type="text" name="label" placeholder="Label indikator baru..."
                           class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm text-gray-900" required>
                    <input type="number" name="sort_order" value="{{ $section->items->count() + 1 }}"
                           class="w-16 border border-gray-200 rounded px-2 py-1.5 text-sm text-gray-900 text-center">
                    <button type="submit" class="px-3 py-1.5 rounded text-sm font-semibold btn-mk-primary">+ Item</button>
                </form>
            </div>
            @endrole
        </div>
        @endforeach

        @role('Owner')
        <div class="bg-white shadow-sm rounded-lg p-5">
            <div class="text-sm font-semibold text-gray-700 mb-3">+ Tambah Seksi Baru</div>
            <form method="POST" action="{{ route('report-templates.sections.store', $reportTemplate) }}" class="flex gap-2">
                @csrf
                <input type="text" name="title" placeholder="Judul seksi..."
                       class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900" required>
                <input type="number" name="sort_order" value="{{ $reportTemplate->sections->count() + 1 }}"
                       class="w-16 border border-gray-200 rounded-lg px-2 py-2 text-sm text-gray-900 text-center">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">Tambah</button>
            </form>
        </div>
        @endrole

        <div class="mt-4">
            <a href="{{ route('report-templates.index') }}" class="text-sm text-gray-500 hover:underline">&larr; Kembali</a>
        </div>
    </div>
</x-app-layout>
