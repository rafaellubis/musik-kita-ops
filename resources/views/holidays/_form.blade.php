@php $holiday = $holiday ?? null; @endphp

@if($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded">
        <ul class="text-sm text-red-700 list-disc pl-5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium">Tanggal <span class="text-red-500">*</span></label>
        <input type="date" name="date" required
               value="{{ old('date', $holiday?->date?->format('Y-m-d') ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
    </div>
    <div>
        <label class="block text-sm font-medium">Tipe <span class="text-red-500">*</span></label>
        <select name="type" required class="mt-1 block w-full border-gray-300 rounded-md">
            @php $types = ['Nasional', 'Cuti Bersama', 'Internal']; @endphp
            <option value="">— Pilih —</option>
            @foreach($types as $t)
                <option value="{{ $t }}"
                    {{ old('type', $holiday->type ?? '') == $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium">Nama Hari Libur <span class="text-red-500">*</span></label>
        <input type="text" name="name" required maxlength="100"
               value="{{ old('name', $holiday->name ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md"
               placeholder="Tahun Baru Masehi">
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium">Catatan</label>
        <textarea name="notes" rows="2"
                  class="mt-1 block w-full border-gray-300 rounded-md"
                  placeholder="Tanggal estimasi, perlu konfirmasi SKB Menteri">{{ old('notes', $holiday->notes ?? '') }}</textarea>
    </div>
    <div class="flex items-end">
        <label class="inline-flex items-center">
            <input type="checkbox" name="is_active" value="1"
                {{ old('is_active', $holiday->is_active ?? true) ? 'checked' : '' }}
                class="rounded border-gray-300">
            <span class="ml-2 text-sm">Aktif</span>
        </label>
    </div>
</div>