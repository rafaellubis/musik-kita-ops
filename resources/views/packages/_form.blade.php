@php $package = $package ?? null; @endphp
@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded">
        <ul class="text-sm text-red-700 list-disc pl-5">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium">Code <span class="text-red-500">*</span></label>
        <input type="text" name="code" required maxlength="30"
               value="{{ old('code', $package->code ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md font-mono uppercase"
               placeholder="PIANO_REG_BASIC">
    </div>
    <div>
        <label class="block text-sm font-medium">Instrumen <span class="text-red-500">*</span></label>
        <select name="instrument_id" required class="mt-1 block w-full border-gray-300 rounded-md">
            <option value="">— Pilih —</option>
            @foreach($instruments as $instr)
                <option value="{{ $instr->id }}"
                    {{ old('instrument_id', $package->instrument_id ?? '') == $instr->id ? 'selected' : '' }}>
                    {{ $instr->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium">Tipe <span class="text-red-500">*</span></label>
        <select name="class_type" required class="mt-1 block w-full border-gray-300 rounded-md">
            @php $types = ['REGULER', 'HOBBY', 'KIDS_CLASS', 'KIDS_CLASS_BUNDLE']; @endphp
            <option value="">— Pilih —</option>
            @foreach($types as $t)
                <option value="{{ $t }}"
                    {{ old('class_type', $package->class_type ?? '') == $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium">Grade</label>
        <input type="text" name="grade" maxlength="10"
               value="{{ old('grade', $package->grade ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md"
               placeholder="BASIC / L1-L4">
    </div>
    <div>
        <label class="block text-sm font-medium">Durasi (menit) <span class="text-red-500">*</span></label>
        <input type="number" name="duration_min" required min="15" max="120"
               value="{{ old('duration_min', $package->duration_min ?? 30) }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
    </div>
    <div>
        <label class="block text-sm font-medium">Harga/Bulan (Rp) <span class="text-red-500">*</span></label>
        <input type="number" name="price_per_month" required min="0"
               value="{{ old('price_per_month', $package->price_per_month ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md"
               placeholder="340000">
    </div>
    <div>
        <label class="block text-sm font-medium">Urutan</label>
        <input type="number" name="sort_order" min="0"
               value="{{ old('sort_order', $package->sort_order ?? 0) }}"
               class="mt-1 block w-32 border-gray-300 rounded-md">
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center">
            <input type="checkbox" name="is_active" value="1"
                {{ old('is_active', $package->is_active ?? true) ? 'checked' : '' }}
                class="rounded border-gray-300">
            <span class="ml-2 text-sm">Aktif</span>
        </label>
    </div>
</div>
