@php $invoiceComponent = $invoiceComponent ?? null; @endphp

@if($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded">
        <ul class="text-sm text-red-700 list-disc pl-5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium">Kode <span class="text-red-500">*</span></label>
        <input type="text" name="code" required maxlength="30"
               value="{{ old('code', $invoiceComponent->code ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md font-mono uppercase"
               placeholder="BUKU / KOSTUM_KIDS"
               oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '')">
        <p class="text-xs text-gray-500 mt-1">Huruf besar, angka, underscore. Contoh: BUKU, KOSTUM_KIDS</p>
    </div>
    <div>
        <label class="block text-sm font-medium">Nama Tampilan <span class="text-red-500">*</span></label>
        <input type="text" name="name" required maxlength="100"
               value="{{ old('name', $invoiceComponent->name ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md"
               placeholder="Buku Materi Piano">
    </div>
    <div>
        <label class="block text-sm font-medium">Harga Default (Rp) <span class="text-red-500">*</span></label>
        <input type="number" name="default_price" required min="0" max="99999999"
               value="{{ old('default_price', $invoiceComponent->default_price ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md"
               placeholder="100000">
        <p class="text-xs text-gray-500 mt-1">Bisa diubah saat input ke invoice murid.</p>
    </div>
    <div>
        <label class="block text-sm font-medium">Urutan Tampil</label>
        <input type="number" name="sort_order" required min="0" max="999"
               value="{{ old('sort_order', $invoiceComponent->sort_order ?? 99) }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
        <p class="text-xs text-gray-500 mt-1">Angka kecil = tampil lebih atas di dropdown.</p>
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium">Keterangan (untuk Admin)</label>
        <textarea name="description" rows="2" maxlength="500"
                  class="mt-1 block w-full border-gray-300 rounded-md"
                  placeholder="Kapan item ini dikenakan, misal: buku semester baru">{{ old('description', $invoiceComponent->description ?? '') }}</textarea>
        <p class="text-xs text-gray-500 mt-1">Tidak tampil di invoice murid, hanya untuk referensi internal.</p>
    </div>
    <div class="flex items-center">
        <label class="inline-flex items-center">
            <input type="checkbox" name="is_active" value="1"
                {{ old('is_active', $invoiceComponent->is_active ?? true) ? 'checked' : '' }}
                class="rounded border-gray-300">
            <span class="ml-2 text-sm">Aktif (muncul di dropdown invoice)</span>
        </label>
    </div>
</div>
