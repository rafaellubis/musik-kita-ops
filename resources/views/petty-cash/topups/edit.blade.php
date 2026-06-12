<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Edit Isi Saldo</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $topup->topup_number }}</div>
            </div>
            <a href="{{ route('petty-cash.topups.show', $topup) }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6 max-w-2xl">
            <form method="POST" action="{{ route('petty-cash.topups.update', $topup) }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Tanggal Isi Saldo <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="topup_date" required
                           value="{{ old('topup_date', $topup->topup_date->toDateString()) }}"
                           max="{{ now()->toDateString() }}"
                           class="mt-1 block w-full border-mk-border rounded @error('topup_date') border-red-500 @enderror">
                    @error('topup_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Keterangan <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="description" required maxlength="255"
                           value="{{ old('description', $topup->description) }}"
                           class="mt-1 block w-full border-mk-border rounded @error('description') border-red-500 @enderror">
                    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">
                        Nominal (Rp) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="amount" required min="1"
                           value="{{ old('amount', $topup->amount) }}"
                           class="mt-1 block w-full border-mk-border rounded @error('amount') border-red-500 @enderror">
                    @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-mk-muted">Ganti Foto Bukti (opsional)</label>
                    @if($topup->receipt_image)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $topup->receipt_image) }}"
                                 class="h-20 rounded border" alt="Foto saat ini">
                        </div>
                    @endif
                    <input type="file" name="receipt_image" accept="image/*" class="block w-full text-sm">
                    @error('receipt_image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-mk-muted">Catatan</label>
                    <textarea name="notes" rows="2" maxlength="1000"
                              class="mt-1 block w-full border-mk-border rounded text-sm">{{ old('notes', $topup->notes) }}</textarea>
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="{{ route('petty-cash.topups.show', $topup) }}"
                       class="px-4 py-2 bg-mk-surface hover:bg-mk-surfaceHover rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-bold transition-colors btn-mk-primary">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
