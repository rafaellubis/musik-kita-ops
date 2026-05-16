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
        <label class="block text-sm font-medium">Kode Ruangan <span class="text-red-500">*</span></label>
        <input type="text" name="code" required maxlength="10"
               value="{{ old('code', $room->code ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md font-mono uppercase"
               placeholder="R1 / R10"
               oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')">
        <p class="text-xs text-gray-500 mt-1">Hanya huruf besar dan angka. Contoh: R1, R10</p>
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
        <p class="text-xs text-gray-500 mt-1">Isi 4 untuk ruang Kids Class, 1 untuk ruang privat.</p>
    </div>
    <div></div>

    {{-- Multi-checkbox instrumen yang didukung --}}
    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-2">
            Instrumen yang Didukung
        </label>
        <div class="grid grid-cols-3 md:grid-cols-4 gap-2">
            @foreach($instruments as $instrument)
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox"
                       name="supported_instruments[]"
                       value="{{ $instrument->name }}"
                       {{ in_array($instrument->name, old('supported_instruments', $room->supported_instruments ?? [])) ? 'checked' : '' }}
                       class="rounded border-gray-300">
                <span class="text-sm">{{ $instrument->name }}</span>
            </label>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 mt-1">Pilih instrumen apa saja yang bisa diajarkan di ruangan ini.</p>
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
