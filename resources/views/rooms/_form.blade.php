@php $room = $room ?? null; @endphp

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
        <input type="text" name="code" required maxlength="10"
               value="{{ old('code', $room->code ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md font-mono uppercase"
               placeholder="R1 / R2 / R10"
			   oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')">
        <p class="text-xs text-gray-500 mt-1">Hanya huruf besar dan angka. Contoh: R1, STUDIO1</p>
    </div>
    <div>
        <label class="block text-sm font-medium">Nama Ruangan <span class="text-red-500">*</span></label>
        <input type="text" name="name" required maxlength="50"
               value="{{ old('name', $room->name ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md"
               placeholder="Studio 1">
    </div>
    <div>
        <label class="block text-sm font-medium">Kapasitas <span class="text-red-500">*</span></label>
        <input type="number" name="capacity" required min="1" max="20"
               value="{{ old('capacity', $room->capacity ?? 1) }}"
               class="mt-1 block w-full border-gray-300 rounded-md">
        <p class="text-xs text-gray-500 mt-1">Jumlah orang maksimal di ruangan ini.</p>
    </div>
    <div></div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-2">Fasilitas</label>
        <div class="space-y-2">
            <label class="inline-flex items-center">
                <input type="checkbox" name="has_piano" value="1"
                    {{ old('has_piano', $room->has_piano ?? false) ? 'checked' : '' }}
                    class="rounded border-gray-300">
                <span class="ml-2 text-sm">Piano internal</span>
            </label>
            <br>
            <label class="inline-flex items-center">
                <input type="checkbox" name="has_drum" value="1"
                    {{ old('has_drum', $room->has_drum ?? false) ? 'checked' : '' }}
                    class="rounded border-gray-300">
                <span class="ml-2 text-sm">Drum kit</span>
            </label>
            <br>
            <label class="inline-flex items-center">
                <input type="checkbox" name="has_amplifier" value="1"
                    {{ old('has_amplifier', $room->has_amplifier ?? false) ? 'checked' : '' }}
                    class="rounded border-gray-300">
                <span class="ml-2 text-sm">Amplifier</span>
            </label>
        </div>
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium">Catatan</label>
        <textarea name="notes" rows="2"
                  class="mt-1 block w-full border-gray-300 rounded-md"
                  placeholder="Catatan khusus tentang ruangan">{{ old('notes', $room->notes ?? '') }}</textarea>
    </div>

    <div class="flex items-end">
        <label class="inline-flex items-center">
            <input type="checkbox" name="is_active" value="1"
                {{ old('is_active', $room->is_active ?? true) ? 'checked' : '' }}
                class="rounded border-gray-300">
            <span class="ml-2 text-sm">Ruangan Aktif</span>
        </label>
    </div>
</div>