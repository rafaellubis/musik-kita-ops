@php $teacher = $teacher ?? null; @endphp
@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded">
        <ul class="text-sm text-red-700 list-disc pl-5">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
 
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium">Kode <span class="text-red-500">*</span></label>
        <input type="text" name="code" required maxlength="10"
               value="{{ old('code', $teacher->code ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md font-mono uppercase"
               placeholder="T19">
    </div>
    <div>
        <label class="block text-sm font-medium">Nama <span class="text-red-500">*</span></label>
        <input type="text" name="name" required maxlength="100"
               value="{{ old('name', $teacher->name ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
    </div>
    <div>
        <label class="block text-sm font-medium">Email</label>
        <input type="email" name="email" maxlength="100"
               value="{{ old('email', $teacher->email ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
    </div>
    <div>
        <label class="block text-sm font-medium">Phone</label>
        <input type="text" name="phone" maxlength="20"
               value="{{ old('phone', $teacher->phone ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
    </div>
    <div>
        <label class="block text-sm font-medium">Bank</label>
        <input type="text" name="bank_name" maxlength="50"
               value="{{ old('bank_name', $teacher->bank_name ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md"
               placeholder="BCA / Mandiri">
    </div>
    <div>
        <label class="block text-sm font-medium">No. Rekening</label>
        <input type="text" name="bank_account" maxlength="30"
               value="{{ old('bank_account', $teacher->bank_account ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
    </div>
    <div>
        <label class="block text-sm font-medium">Nama Pemilik Rekening</label>
        <input type="text" name="bank_account_holder" maxlength="100"
               value="{{ old('bank_account_holder', $teacher->bank_account_holder ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md"
               placeholder="Nama sesuai buku tabungan">
    </div>
    <div>
        <label class="block text-sm font-medium">Tgl Bergabung</label>
        <input type="date" name="joined_date"
               value="{{ old('joined_date', $teacher?->joined_date?->format('Y-m-d') ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center">
            <input type="checkbox" name="is_active" value="1"
                {{ old('is_active', $teacher->is_active ?? true) ? 'checked' : '' }}>
            <span class="ml-2 text-sm">Aktif</span>
        </label>
    </div>
</div>
 
<div class="mt-6">
    <label class="block text-sm font-medium mb-2">Instrumen yang Dikuasai (centang yang bisa, ★ untuk utama)</label>
    @php
        $selected = old('instruments', $teacher?->instruments->pluck('id')->toArray() ?? []);
        $primary = old('primary_instrument', $teacher?->instruments->where('pivot.is_primary', true)->first()?->id);
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 border rounded p-3">
        @foreach($instruments as $instr)
            <div class="flex items-center justify-between p-2 border rounded">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="instruments[]" value="{{ $instr->id }}"
                        {{ in_array($instr->id, $selected) ? 'checked' : '' }}
                        class="rounded mr-2">
                    <span>{{ $instr->name }}</span>
                </label>
                <label class="inline-flex items-center text-xs">
                    <input type="radio" name="primary_instrument" value="{{ $instr->id }}"
                        {{ $primary == $instr->id ? 'checked' : '' }} class="mr-1">
                    <span>★</span>
                </label>
            </div>
        @endforeach
    </div>
</div>
 
<div class="mt-4">
    <label class="block text-sm font-medium">Catatan</label>
    <textarea name="notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-md">{{ old('notes', $teacher->notes ?? '') }}</textarea>
</div>
