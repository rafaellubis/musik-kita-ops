<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Catat Pengeluaran Baru</h2>
            <a href="{{ route('expenses.index') }}" class="text-sm text-gray-600 hover:underline">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form method="POST" action="{{ route('expenses.store') }}"
                      enctype="multipart/form-data">
                    @csrf

                    {{-- Tanggal --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Tanggal Pengeluaran <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="expense_date" required
                               value="{{ old('expense_date', now()->toDateString()) }}"
                               max="{{ now()->toDateString() }}"
                               class="mt-1 block w-full border-gray-300 rounded @error('expense_date') border-red-500 @enderror">
                        @error('expense_date')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Kategori --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Kategori <span class="text-red-500">*</span>
                        </label>
                        <select name="expense_category_id" required
                                class="mt-1 block w-full border-gray-300 rounded @error('expense_category_id') border-red-500 @enderror">
                            <option value="">— Pilih kategori —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}"
                                        {{ old('expense_category_id') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('expense_category_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Keterangan --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Keterangan <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="description" required maxlength="255"
                               value="{{ old('description') }}"
                               placeholder="Contoh: Bayar listrik Mei 2026"
                               class="mt-1 block w-full border-gray-300 rounded @error('description') border-red-500 @enderror">
                        @error('description')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Jumlah + Metode --}}
                    <div class="mb-4 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Jumlah (Rp) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="amount" required
                                   min="1" max="999999999"
                                   value="{{ old('amount') }}"
                                   placeholder="150000"
                                   class="mt-1 block w-full border-gray-300 rounded @error('amount') border-red-500 @enderror">
                            @error('amount')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Metode Pembayaran <span class="text-red-500">*</span>
                            </label>
                            <select name="payment_method" required
                                    class="mt-1 block w-full border-gray-300 rounded @error('payment_method') border-red-500 @enderror">
                                @foreach(\App\Models\Expense::METHODS as $val => $label)
                                    <option value="{{ $val }}" {{ old('payment_method', 'CASH') == $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('payment_method')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Foto Bukti --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Foto Bukti (opsional)
                        </label>
                        <input type="file" name="receipt_image" accept="image/*"
                               class="mt-1 block w-full text-sm @error('receipt_image') border-red-500 @enderror">
                        <p class="text-xs text-gray-400 mt-1">Maks 2 MB, JPG/PNG. Foto struk atau nota.</p>
                        @error('receipt_image')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Catatan --}}
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700">Catatan (opsional)</label>
                        <textarea name="notes" rows="2" maxlength="1000"
                                  class="mt-1 block w-full border-gray-300 rounded text-sm">{{ old('notes') }}</textarea>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                            Simpan Pengeluaran
                        </button>
                        <a href="{{ route('expenses.index') }}"
                           class="px-5 py-2 text-gray-600 hover:text-gray-800 text-sm">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
