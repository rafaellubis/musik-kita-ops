<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Tambah Kategori Pengeluaran</h2>
                <div class="text-xs text-mk-muted mt-0.5">Master Data</div>
            </div>
            <a href="{{ route('expense-categories.index') }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6 max-w-xl">
            <form method="POST" action="{{ route('expense-categories.store') }}">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">
                        Kode <span class="text-red-500">*</span>
                        <span class="text-xs font-normal text-gray-400 ml-1">— huruf kapital & underscore, contoh: SEWA_KECIL</span>
                    </label>
                    <input type="text" name="code" required maxlength="20"
                           value="{{ old('code') }}" placeholder="SEWA"
                           class="mt-1 block w-full border-gray-300 rounded font-mono @error('code') border-red-500 @enderror"
                           oninput="this.value = this.value.toUpperCase()">
                    @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">
                        Nama <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" required maxlength="100"
                           value="{{ old('name') }}" placeholder="Sewa Tempat"
                           class="mt-1 block w-full border-gray-300 rounded @error('name') border-red-500 @enderror">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Deskripsi (opsional)</label>
                    <textarea name="description" rows="2" maxlength="500"
                              class="mt-1 block w-full border-gray-300 rounded text-sm">{{ old('description') }}</textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700">
                        Urutan Tampil
                        <span class="text-xs font-normal text-gray-400 ml-1">— angka kecil tampil lebih atas</span>
                    </label>
                    <input type="number" name="sort_order" min="0" max="9999"
                           value="{{ old('sort_order', 0) }}"
                           class="mt-1 block w-32 border-gray-300 rounded">
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="{{ route('expense-categories.index') }}"
                       class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-bold transition-colors"
                            style="background:#D4A853;color:#1A1000">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
