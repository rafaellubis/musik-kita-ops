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
        <label class="block text-sm font-medium">Code <span class="text-red-500">*</span></label>
        <input type="text" name="code" required maxlength="20"
               value="{{ old('code', $invoiceComponent->code ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md font-mono uppercase"
               placeholder="REG / SPP / DENDA"
			   oninput="this.value = this.value.toUpperCase().replace(/[^A-Z_]/g, '')">
        <p class="text-xs text-gray-500 mt-1">Hanya huruf besar dan underscore. Contoh: KIDS_FP</p>
    </div>
    <div>
        <label class="block text-sm font-medium">Nama Tampilan <span class="text-red-500">*</span></label>
        <input type="text" name="name" required maxlength="100"
               value="{{ old('name', $invoiceComponent->name ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md"
               placeholder="SPP Bulanan">
    </div>
    <div>
        <label class="block text-sm font-medium">Tipe <span class="text-red-500">*</span></label>
        <select name="type" required class="mt-1 block w-full border-gray-300 rounded-md">
            @php $types = ['REGULER', 'TRIAL', 'KIDS_FINAL', 'CUTI', 'UJIAN', 'MINI_CONCERT', 'DENDA']; @endphp
            <option value="">— Pilih —</option>
            @foreach($types as $t)
                <option value="{{ $t }}"
                    {{ old('type', $invoiceComponent->type ?? '') == $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium">Jumlah / Rumus <span class="text-red-500">*</span></label>
        <input type="text" name="amount_or_formula" required maxlength="100"
               value="{{ old('amount_or_formula', $invoiceComponent->amount_or_formula ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md font-mono"
               placeholder="250000 atau = harga paket">
        <p class="text-xs text-gray-500 mt-1">Contoh: 250000, = harga paket, 5000/hari</p>
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium">Penjelasan</label>
        <textarea name="description" rows="2"
                  class="mt-1 block w-full border-gray-300 rounded-md"
                  placeholder="Kapan komponen ini muncul di invoice">{{ old('description', $invoiceComponent->description ?? '') }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium">Urutan Tampil</label>
        <input type="number" name="sort_order" required min="0"
               value="{{ old('sort_order', $invoiceComponent->sort_order ?? 99) }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center">
            <input type="checkbox" name="is_active" value="1"
                {{ old('is_active', $invoiceComponent->is_active ?? true) ? 'checked' : '' }}
                class="rounded border-gray-300">
            <span class="ml-2 text-sm">Aktif</span>
        </label>
    </div>
</div>