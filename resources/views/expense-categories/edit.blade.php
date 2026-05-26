<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Edit Kategori Pengeluaran</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $expenseCategory->name }}</div>
            </div>
            <a href="{{ route('expense-categories.index') }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6 max-w-xl">
            <form method="POST" action="{{ route('expense-categories.update', $expenseCategory) }}">
                @csrf @method('PATCH')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Kode <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="code" required maxlength="20"
                           value="{{ old('code', $expenseCategory->code) }}"
                           class="mt-1 block w-full border-mk-border rounded font-mono @error('code') border-red-500 @enderror"
                           oninput="this.value = this.value.toUpperCase()">
                    @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">Nama <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required maxlength="100"
                           value="{{ old('name', $expenseCategory->name) }}"
                           class="mt-1 block w-full border-mk-border rounded @error('name') border-red-500 @enderror">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">Deskripsi</label>
                    <textarea name="description" rows="2" maxlength="500"
                              class="mt-1 block w-full border-mk-border rounded text-sm">{{ old('description', $expenseCategory->description) }}</textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">Urutan Tampil</label>
                    <input type="number" name="sort_order" min="0" max="9999"
                           value="{{ old('sort_order', $expenseCategory->sort_order) }}"
                           class="mt-1 block w-32 border-mk-border rounded">
                </div>

                <div class="mb-6 flex items-center gap-2">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                           {{ old('is_active', $expenseCategory->is_active) ? 'checked' : '' }}
                           class="rounded border-mk-border">
                    <label for="is_active" class="text-sm text-mk-muted">Kategori aktif</label>
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="{{ route('expense-categories.index') }}"
                       class="px-4 py-2 bg-mk-surface hover:bg-mk-surfaceHover rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-bold transition-colors btn-mk-primary"
                            >
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
