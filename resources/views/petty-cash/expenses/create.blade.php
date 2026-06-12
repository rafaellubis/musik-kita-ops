<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Catat Pengeluaran Petty Cash</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    Saldo tersedia: Rp {{ number_format($balance, 0, ',', '.') }}
                </div>
            </div>
            <a href="{{ route('petty-cash.index') }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6 max-w-2xl">
            <form method="POST" action="{{ route('petty-cash.expenses.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Tanggal Pengeluaran <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="expense_date" required
                           value="{{ old('expense_date', now()->toDateString()) }}"
                           max="{{ now()->toDateString() }}"
                           class="mt-1 block w-full border-mk-border rounded @error('expense_date') border-red-500 @enderror">
                    @error('expense_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Kategori <span class="text-red-500">*</span>
                    </label>
                    <select name="expense_category_id" required
                            class="mt-1 block w-full border-mk-border rounded @error('expense_category_id') border-red-500 @enderror">
                        <option value="">— Pilih kategori —</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}"
                                    {{ old('expense_category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('expense_category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Keterangan <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="description" required maxlength="255"
                           value="{{ old('description') }}"
                           placeholder="Contoh: Beli ATK studio"
                           class="mt-1 block w-full border-mk-border rounded @error('description') border-red-500 @enderror">
                    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Jumlah (Rp) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="amount" required min="1" max="{{ max(1, $balance) }}"
                           value="{{ old('amount') }}"
                           placeholder="50000"
                           class="mt-1 block w-full border-mk-border rounded @error('amount') border-red-500 @enderror">
                    @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">Foto Bukti (opsional)</label>
                    <input type="file" name="receipt_image" accept="image/*"
                           class="mt-1 block w-full text-sm @error('receipt_image') border-red-500 @enderror">
                    <p class="text-xs text-mk-dim mt-1">Maks 2 MB, JPG/PNG.</p>
                    @error('receipt_image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-mk-muted">Catatan (opsional)</label>
                    <textarea name="notes" rows="2" maxlength="1000"
                              class="mt-1 block w-full border-mk-border rounded text-sm">{{ old('notes') }}</textarea>
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="{{ route('petty-cash.index') }}"
                       class="px-4 py-2 bg-mk-surface hover:bg-mk-surfaceHover rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-bold transition-colors btn-mk-primary">
                        Simpan Pengeluaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
