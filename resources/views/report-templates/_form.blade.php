<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Instrumen <span class="text-red-500">*</span>
    </label>
    <select name="instrument_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900" required>
        <option value="">-- Pilih Instrumen --</option>
        @foreach($instruments as $inst)
            <option value="{{ $inst->id }}" {{ old('instrument_id', $template?->instrument_id) == $inst->id ? 'selected' : '' }}>
                {{ $inst->name }}
            </option>
        @endforeach
    </select>
    @error('instrument_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Nama Template <span class="text-red-500">*</span>
    </label>
    <input type="text" name="name" value="{{ old('name', $template?->name) }}"
           placeholder="cth: Template Vocal"
           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900" required>
    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Deskripsi</label>
    <textarea name="description" rows="2"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old('description', $template?->description) }}</textarea>
</div>

<div class="flex gap-4 mb-4">
    <div class="flex-1">
        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Urutan</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $template?->sort_order ?? 0) }}"
               min="0" max="999" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    </div>
    <div class="flex items-end mb-0 pb-1">
        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" name="is_active" value="1"
                   {{ old('is_active', $template?->is_active ?? true) ? 'checked' : '' }}>
            Aktif
        </label>
    </div>
</div>
