<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Tambah Kategori Pengeluaran</h2>
            <a href="{{ route('expense-categories.index') }}" class="text-sm text-gray-600 hover:underline">← Kembali</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
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

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700">
                            Urutan Tampil
                            <span class="text-xs font-normal text-gray-400 ml-1">— angka kecil tampil lebih atas</span>
                        </label>
                        <input type="number" name="sort_order" min="0" max="9999"
                               value="{{ old('sort_order', 0) }}"
                               class="mt-1 block w-32 border-gray-300 rounded">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                            Simpan
                        </button>
                        <a href="{{ route('expense-categories.index') }}"
                           class="px-5 py-2 text-gray-600 hover:text-gray-800 text-sm">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
