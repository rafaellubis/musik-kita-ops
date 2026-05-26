<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Tambah Instrumen</h2>
                <div class="text-xs text-mk-muted mt-0.5">Master Data</div>
            </div>
            <a href="{{ route('instruments.index') }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6 max-w-2xl">
            <form action="{{ route('instruments.store') }}" method="POST">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">Code (huruf kapital)</label>
                    <input type="text" name="code" value="{{ old('code') }}"
                           class="mt-1 block w-full border-mk-border rounded px-3 py-2 font-mono"
                           placeholder="PIANO">
                    @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">Nama Tampil</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="mt-1 block w-full border-mk-border rounded px-3 py-2"
                           placeholder="Piano">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">Deskripsi (Opsional)</label>
                    <textarea name="description" rows="2"
                              class="mt-1 block w-full border-mk-border rounded px-3 py-2">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">Urutan Tampil</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 99) }}"
                           class="mt-1 block w-32 border-mk-border rounded px-3 py-2">
                    @error('sort_order') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-5">
                    <label class="inline-flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', '1') ? 'checked' : '' }} class="mr-2">
                        <span class="text-sm">Aktif</span>
                    </label>
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="{{ route('instruments.index') }}"
                       class="px-4 py-2 bg-mk-surface hover:bg-mk-surfaceHover rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-bold transition-colors btn-mk-primary"
                            >Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
