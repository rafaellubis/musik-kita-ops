<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Edit Pengeluaran</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $expense->expense_number }}</div>
            </div>
            <a href="{{ route('expenses.show', $expense) }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6 max-w-2xl">

            <form method="POST" action="{{ route('expenses.update', $expense) }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Tanggal Pengeluaran <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="expense_date" required
                           value="{{ old('expense_date', $expense->expense_date->toDateString()) }}"
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
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}"
                                    {{ old('expense_category_id', $expense->expense_category_id) == $cat->id ? 'selected' : '' }}>
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
                           value="{{ old('description', $expense->description) }}"
                           class="mt-1 block w-full border-mk-border rounded @error('description') border-red-500 @enderror">
                    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Jumlah (Rp) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="amount" required
                           min="1" max="999999999"
                           value="{{ old('amount', $expense->amount) }}"
                           class="mt-1 block w-full border-mk-border rounded @error('amount') border-red-500 @enderror">
                    @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                @if($expense->payment_method === 'CASH')
                    {{-- Legacy: record CASH lama tetap tampil read-only, metode tidak diubah saat simpan --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-mk-muted">Metode Pembayaran</label>
                        <input type="text" readonly value="CASH (Tunai — data lama)"
                               class="mt-1 block w-full border-mk-border rounded bg-mk-surface text-mk-muted cursor-not-allowed">
                        <p class="text-xs text-mk-dim mt-1">Record tunai lama tidak dapat diubah metodenya. Untuk pengeluaran tunai baru, gunakan modul Petty Cash.</p>
                    </div>
                @else
                    <input type="hidden" name="payment_method" value="TRANSFER">
                    <p class="text-xs text-mk-dim mb-4">Pengeluaran operasional dicatat via transfer bank.</p>
                @endif
                @error('payment_method')<p class="mb-4 text-xs text-red-600">{{ $message }}</p>@enderror

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Ganti Foto Bukti (opsional)
                    </label>
                    @if($expense->receipt_image)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $expense->receipt_image) }}"
                                 class="h-20 rounded border" alt="Foto saat ini">
                            <span class="text-xs text-mk-dim ml-2">Foto saat ini</span>
                        </div>
                    @endif
                    <input type="file" name="receipt_image" accept="image/*" class="block w-full text-sm">
                    <p class="text-xs text-mk-dim mt-1">Biarkan kosong untuk mempertahankan foto lama.</p>
                    @error('receipt_image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-mk-muted">Catatan</label>
                    <textarea name="notes" rows="2" maxlength="1000"
                              class="mt-1 block w-full border-mk-border rounded text-sm">{{ old('notes', $expense->notes) }}</textarea>
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="{{ route('expenses.show', $expense) }}"
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
