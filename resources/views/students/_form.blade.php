@php $student = $student ?? null; @endphp

{{-- ============= ERROR SUMMARY ============= --}}
@if($errors->any())
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded">
        <strong class="text-red-700">Ada {{ count($errors) }} error pada form:</strong>
        <ul class="text-sm text-red-700 list-disc pl-5 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ============= SECTION 1: IDENTITAS ============= --}}
<fieldset class="mb-8">
    <legend class="text-lg font-bold text-mk-text border-b w-full pb-2 mb-4">
        1. Identitas
    </legend>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="md:col-span-2">
            <label class="block text-sm font-medium">
                Nama Lengkap <span class="text-red-500">*</span>
            </label>
            <input type="text" name="full_name" required maxlength="100"
                   value="{{ old('full_name', $student?->full_name) }}"
                   class="mt-1 block w-full border-mk-border rounded-md shadow-sm"
                   placeholder="Nama sesuai akte/KTP">
            @error('full_name')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Nama Panggilan</label>
            <input type="text" name="nickname" maxlength="30"
                   value="{{ old('nickname', $student?->nickname) }}"
                   class="mt-1 block w-full border-mk-border rounded-md shadow-sm">
            @error('nickname')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">
                Jenis Kelamin <span class="text-red-500">*</span>
            </label>
            <select name="gender" required
                    class="mt-1 block w-full border-mk-border rounded-md shadow-sm">
                <option value="">— Pilih —</option>
                <option value="L" {{ old('gender', $student?->gender) == 'L' ? 'selected' : '' }}>
                    Laki-laki
                </option>
                <option value="P" {{ old('gender', $student?->gender) == 'P' ? 'selected' : '' }}>
                    Perempuan
                </option>
            </select>
            @error('gender')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Tanggal Lahir</label>
            <input type="date" name="birth_date"
                   value="{{ old('birth_date', $student?->birth_date?->format('Y-m-d')) }}"
                   class="mt-1 block w-full border-mk-border rounded-md shadow-sm">
            <p class="text-xs text-mk-dim mt-1">
                Untuk hitung umur dan validasi paket Kids Class.
            </p>
            @error('birth_date')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

    </div>
</fieldset>

{{-- ============= SECTION 2: KONTAK ============= --}}
<fieldset class="mb-8">
    <legend class="text-lg font-bold text-mk-text border-b w-full pb-2 mb-4">
        2. Kontak & Personal
    </legend>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
            <label class="block text-sm font-medium">No. HP Murid</label>
            <input type="text" name="phone" maxlength="20"
                   value="{{ old('phone', $student?->phone) }}"
                   class="mt-1 block w-full border-mk-border rounded-md shadow-sm"
                   placeholder="08123...">
            <p class="text-xs text-mk-dim mt-1">Kosongkan kalau murid masih anak-anak.</p>
            @error('phone')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Email Murid</label>
            <input type="email" name="email" maxlength="100"
                   value="{{ old('email', $student?->email) }}"
                   class="mt-1 block w-full border-mk-border rounded-md shadow-sm">
            @error('email')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Alamat</label>
            <textarea name="address" rows="2"
                      class="mt-1 block w-full border-mk-border rounded-md shadow-sm"
                      placeholder="Jalan, RT/RW, Kel, Kec, Kota">{{ old('address', $student?->address) }}</textarea>
            @error('address')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Catatan Internal</label>
            <textarea name="notes" rows="2"
                      class="mt-1 block w-full border-mk-border rounded-md shadow-sm"
                      placeholder="Info penting untuk admin">{{ old('notes', $student?->notes) }}</textarea>
            @error('notes')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

    </div>
</fieldset>

{{-- ============= SECTION 3: ORANG TUA ============= --}}
<fieldset class="mb-8">
    <legend class="text-lg font-bold text-mk-text border-b w-full pb-2 mb-4">
        3. Orang Tua / Wali
        <span class="text-xs font-normal text-mk-dim">(wajib untuk Kids Class)</span>
    </legend>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
            <label class="block text-sm font-medium">Nama Orang Tua / Wali</label>
            <input type="text" name="parent_name" maxlength="100"
                   value="{{ old('parent_name', $student?->parent_name) }}"
                   class="mt-1 block w-full border-mk-border rounded-md shadow-sm">
            @error('parent_name')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Hubungan</label>
            <select name="parent_relationship"
                    class="mt-1 block w-full border-mk-border rounded-md shadow-sm">
                <option value="">— Pilih —</option>
                @foreach(['Ayah', 'Ibu', 'Wali'] as $rel)
                    <option value="{{ $rel }}"
                        {{ old('parent_relationship', $student?->parent_relationship) == $rel ? 'selected' : '' }}>
                        {{ $rel }}
                    </option>
                @endforeach
            </select>
            @error('parent_relationship')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">No. HP Orang Tua/No. WhatsApp</label>
            <input type="text" name="parent_phone" maxlength="20"
                   value="{{ old('parent_phone', $student?->parent_phone) }}"
                   class="mt-1 block w-full border-mk-border rounded-md shadow-sm">
            @error('parent_phone')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Email Orang Tua</label>
            <input type="email" name="parent_email" maxlength="100"
                   value="{{ old('parent_email', $student?->parent_email) }}"
                   class="mt-1 block w-full border-mk-border rounded-md shadow-sm">
            @error('parent_email')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

    </div>
</fieldset>

{{-- ============= SECTION 4: STATUS BELAJAR ============= --}}
<fieldset class="mb-4">
    <legend class="text-lg font-bold text-mk-text border-b w-full pb-2 mb-4">
        4. Status Belajar & Paket
    </legend>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Status: editable di create, read-only di edit --}}
<div class="md:col-span-2">
    <label class="block text-sm font-medium">
        Status
        @if(($mode ?? 'create') === 'create')
            <span class="text-red-500">*</span>
        @endif
    </label>

    @if(($mode ?? 'create') === 'create')
        {{-- Mode Create: status selalu Calon, tidak bisa dipilih --}}
        <input type="hidden" name="status" value="Calon">
        <div class="mt-1 flex items-center gap-3">
            <span class="px-4 py-2 rounded-md border text-sm font-medium bg-mk-surface text-mk-muted border-mk-border">
                Calon
            </span>
            <span class="text-xs text-mk-dim">
                Murid baru selalu mulai sebagai Calon. Ubah status lewat tombol aksi di halaman Detail.
            </span>
        </div>

    @else
        {{-- Mode Edit: status read-only, tidak bisa diubah --}}
        @php
            $statusColors = [
                'Calon' => 'bg-gray-100 text-gray-700 border-gray-300',
                'Trial' => 'bg-purple-100 text-purple-700 border-purple-300',
                'Aktif' => 'bg-green-100 text-green-700 border-green-300',
                'Cuti' => 'bg-amber-100 text-amber-700 border-amber-300',
                'Selesai' => 'bg-blue-100 text-blue-700 border-blue-300',
                'Mengundurkan Diri' => 'bg-red-100 text-red-700 border-red-300',
            ];
            $currentColor = $statusColors[$student?->status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
        @endphp
        <div class="mt-1 flex items-center gap-3">
            <span class="px-4 py-2 rounded-md border text-sm font-medium {{ $currentColor }}">
                {{ $student?->status ?? '—' }}
            </span>
            <span class="text-xs text-mk-dim">
                🔒 Status hanya bisa diubah lewat tombol aksi di halaman Detail.
            </span>
        </div>
    @endif
</div>

    </div>
</fieldset>
