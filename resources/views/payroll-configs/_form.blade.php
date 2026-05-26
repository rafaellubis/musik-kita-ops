@php $payrollConfig = $payrollConfig ?? null; @endphp

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
        <input type="text" name="scenario_code" required maxlength="30"
               value="{{ old('scenario_code', $payrollConfig->scenario_code ?? '') }}"
               class="mt-1 block w-full border-mk-border rounded-md font-mono uppercase"
               placeholder="H_REG, H_KIDS, dll">
    </div>
    <div>
        <label class="block text-sm font-medium">Nama Tampilan <span class="text-red-500">*</span></label>
        <input type="text" name="scenario_name" required maxlength="100"
               value="{{ old('scenario_name', $payrollConfig->scenario_name ?? '') }}"
               class="mt-1 block w-full border-mk-border rounded-md"
               placeholder="Honor Reguler">
    </div>
    <div>
        <label class="block text-sm font-medium">Tipe Rumus <span class="text-red-500">*</span></label>
        <select name="formula_type" required class="mt-1 block w-full border-mk-border rounded-md">
            @php $types = ['PERCENTAGE', 'PER_STUDENT', 'FIXED', 'CONSTANT']; @endphp
            <option value="">— Pilih —</option>
            @foreach($types as $t)
                <option value="{{ $t }}"
                    {{ old('formula_type', $payrollConfig->formula_type ?? '') == $t ? 'selected' : '' }}>
                    {{ $t }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium">Value / Rumus <span class="text-red-500">*</span></label>
        <input type="text" name="value_or_formula" required maxlength="100"
               value="{{ old('value_or_formula', $payrollConfig->value_or_formula ?? '') }}"
               class="mt-1 block w-full border-mk-border rounded-md font-mono"
               placeholder="50% × harga ÷ 4">
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium">Deskripsi</label>
        <textarea name="description" rows="2"
                  class="mt-1 block w-full border-mk-border rounded-md">{{ old('description', $payrollConfig->description ?? '') }}</textarea>
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center">
            <input type="checkbox" name="is_active" value="1"
                {{ old('is_active', $payrollConfig->is_active ?? true) ? 'checked' : '' }}
                class="rounded border-mk-border">
            <span class="ml-2 text-sm">Aktif</span>
        </label>
    </div>
</div>