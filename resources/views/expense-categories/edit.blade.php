<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Edit Kategori — {{ $expenseCategory->name }}</h2>
            <a href="{{ route('expense-categories.index') }}" class="text-sm text-gray-600 hover:underline">← Kembali</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('expense-categories.update', $expenseCategory) }}">
                    @csrf @method('PATCH')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Kode <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="code" required maxlength="20"
                               value="{{ old('code', $expenseCategory->code) }}"
                               class="mt-1 block w-full border-gray-300 rounded font-mono @error('code') border-red-500 @enderror"
                               oninput="this.value = this.value.toUpperCase()">
                        @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Nama <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required maxlength="100"
                               value="{{ old('name', $expenseCategory->name) }}"
                               class="mt-1 block w-full border-gray-300 rounded @error('name') border-red-500 @enderror">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
                        <textarea name="description" rows="2" maxlength="500"
                                  class="mt-1 block w-full border-gray-300 rounded text-sm">{{ old('description', $expenseCategory->description) }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Urutan Tampil</label>
                        <input type="number" name="sort_order" min="0" max="9999"
                               value="{{ old('sort_order', $expenseCategory->sort_order) }}"
                               class="mt-1 block w-32 border-gray-300 rounded">
                    </div>

                    <div class="mb-5 flex items-center gap-2">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               {{ old('is_active', $expenseCategory->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300">
                        <label for="is_active" class="text-sm text-gray-700">Kategori aktif</label>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                            Simpan Perubahan
                        </button>
                        <a href="{{ route('expense-categories.index') }}"
                           class="px-5 py-2 text-gray-600 hover:text-gray-800 text-sm">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
