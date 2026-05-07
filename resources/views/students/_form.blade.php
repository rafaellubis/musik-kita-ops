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
    <legend class="text-lg font-bold text-gray-800 border-b w-full pb-2 mb-4">
        1. Identitas
    </legend>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="md:col-span-2">
            <label class="block text-sm font-medium">
                Nama Lengkap <span class="text-red-500">*</span>
            </label>
            <input type="text" name="full_name" required maxlength="100"
                   value="{{ old('full_name', $student?->full_name) }}"
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                   placeholder="Nama sesuai akte/KTP">
            @error('full_name')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Nama Panggilan</label>
            <input type="text" name="nickname" maxlength="30"
                   value="{{ old('nickname', $student?->nickname) }}"
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            @error('nickname')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">
                Jenis Kelamin <span class="text-red-500">*</span>
            </label>
            <select name="gender" required
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
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
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            <p class="text-xs text-gray-500 mt-1">
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
    <legend class="text-lg font-bold text-gray-800 border-b w-full pb-2 mb-4">
        2. Kontak & Personal
    </legend>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
            <label class="block text-sm font-medium">No. HP Murid</label>
            <input type="text" name="phone" maxlength="20"
                   value="{{ old('phone', $student?->phone) }}"
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                   placeholder="08123...">
            <p class="text-xs text-gray-500 mt-1">Kosongkan kalau murid masih anak-anak.</p>
            @error('phone')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Email Murid</label>
            <input type="email" name="email" maxlength="100"
                   value="{{ old('email', $student?->email) }}"
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            @error('email')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Alamat</label>
            <textarea name="address" rows="2"
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                      placeholder="Jalan, RT/RW, Kel, Kec, Kota">{{ old('address', $student?->address) }}</textarea>
            @error('address')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Catatan Internal</label>
            <textarea name="notes" rows="2"
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                      placeholder="Info penting untuk admin">{{ old('notes', $student?->notes) }}</textarea>
            @error('notes')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

    </div>
</fieldset>

{{-- ============= SECTION 3: ORANG TUA ============= --}}
<fieldset class="mb-8">
    <legend class="text-lg font-bold text-gray-800 border-b w-full pb-2 mb-4">
        3. Orang Tua / Wali
        <span class="text-xs font-normal text-gray-500">(wajib untuk Kids Class)</span>
    </legend>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
            <label class="block text-sm font-medium">Nama Orang Tua / Wali</label>
            <input type="text" name="parent_name" maxlength="100"
                   value="{{ old('parent_name', $student?->parent_name) }}"
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            @error('parent_name')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Hubungan</label>
            <select name="parent_relationship"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
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
            <label class="block text-sm font-medium">No. HP Orang Tua</label>
            <input type="text" name="parent_phone" maxlength="20"
                   value="{{ old('parent_phone', $student?->parent_phone) }}"
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            @error('parent_phone')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Email Orang Tua</label>
            <input type="email" name="parent_email" maxlength="100"
                   value="{{ old('parent_email', $student?->parent_email) }}"
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            @error('parent_email')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

    </div>
</fieldset>

{{-- ============= SECTION 4: STATUS BELAJAR ============= --}}
<fieldset class="mb-4">
    <legend class="text-lg font-bold text-gray-800 border-b w-full pb-2 mb-4">
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
        {{-- Mode Create: dropdown bisa dipilih --}}
        <select name="status" required id="status-select"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                onchange="handleStatusChange(this.value)">
            <option value="">— Pilih —</option>
            @foreach(['Calon', 'Trial', 'Aktif'] as $st)
                <option value="{{ $st }}"
                    {{ old('status', 'Calon') == $st ? 'selected' : '' }}>
                    {{ $st }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">
            Calon = baru inquiry. Trial = sudah dijadwal trial. Aktif = langsung daftar penuh (butuh alasan).
        </p>
        @error('status')
            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
        @enderror

        {{-- Trial Date — muncul kalau status = Trial --}}
        <div class="mt-4" id="field-trial-date"
             style="{{ old('status') === 'Trial' ? '' : 'display:none' }}">
            <label class="block text-sm font-medium">
                Jadwal Trial <span class="text-red-500">*</span>
            </label>
            <input type="datetime-local" name="trial_date"
                   value="{{ old('trial_date') }}"
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                   min="{{ now()->addHour()->format('Y-m-d\TH:i') }}">
            <p class="text-xs text-gray-500 mt-1">
                Durasi trial: 30 menit (semua jenis paket).
            </p>
            @error('trial_date')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Alasan Skip Trial — muncul kalau status = Aktif --}}
        <div class="mt-4 space-y-3" id="field-skip-reason"
             style="{{ old('status') === 'Aktif' ? '' : 'display:none' }}">

            {{-- Dropdown reason_code (enum) --}}
            <div>
                <label class="block text-sm font-medium">
                    Kode Alasan Skip Trial <span class="text-red-500">*</span>
                </label>
                <select name="reason_code"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">— Pilih —</option>
                    @php
                        $reasonOptions = [
                            'walk_in' => 'Walk-in (datang langsung & confident bayar)',
                            'migrasi' => 'Migrasi data dari sistem lama',
                            'reaktivasi' => 'Reaktivasi murid lama',
                            'lulus_kids' => 'Lulus Kids Class lanjut privat',
                        ];
                    @endphp
                    @foreach($reasonOptions as $code => $label)
                        <option value="{{ $code }}"
                            {{ old('reason_code') === $code ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('reason_code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Penjelasan bebas --}}
            <div>
                <label class="block text-sm font-medium">
                    Penjelasan Detail <span class="text-red-500">*</span>
                </label>
                <textarea name="skip_trial_reason" rows="2" maxlength="500"
                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                          placeholder="Tambahkan konteks: nama murid asal, link spreadsheet migrasi, catatan walk-in, dll.">{{ old('skip_trial_reason') }}</textarea>
                <p class="text-xs text-gray-500 mt-1">
                    Akan tercatat di riwayat status (audit trail).
                </p>
                @error('skip_trial_reason')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
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
            <span class="text-xs text-gray-500">
                🔒 Status hanya bisa diubah lewat tombol aksi di halaman Detail.
            </span>
        </div>
    @endif
</div>

        {{-- Paket --}}
		<div>
			<label class="block text-sm font-medium">Paket</label>
			<select name="package_id"
				id="package-select"
				class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
				onchange="handlePackageChange(this)">
			<option value="" data-instrument-id="">— Belum ditentukan —</option>
			@foreach($packages as $pkg)
				<option value="{{ $pkg->id }}"
					data-instrument-id="{{ $pkg->instrument->id }}"
					{{ old('package_id', $student?->package_id) == $pkg->id ? 'selected' : '' }}>
					[{{ $pkg->code }}] {{ $pkg->instrument->name }}
					- {{ $pkg->class_type }}
					@if($pkg->grade) - {{ $pkg->grade }} @endif
					({{ $pkg->formatted_price }})
				</option>
			@endforeach
			</select>
				<p class="text-xs text-gray-500 mt-1">Wajib untuk status Aktif.</p>
			@error('package_id')
				<p class="text-red-600 text-sm mt-1">{{ $message }}</p>
			@enderror
		</div>

        {{-- Guru --}}
<div>
    <label class="block text-sm font-medium">Guru Utama</label>
    <select name="assigned_teacher_id"
            id="teacher-select"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
        {{-- Default: kosong kalau belum pilih paket --}}
        @if($student?->package_id && $student?->assigned_teacher_id)
            {{-- Mode edit: pre-load guru yang sudah assign --}}
            <option value="">— Pilih Guru —</option>
            <option value="{{ $student->assignedTeacher->id }}" selected>
                [{{ $student->assignedTeacher->code }}] {{ $student->assignedTeacher->name }}
            </option>
        @else
            <option value="">— Pilih paket dulu —</option>
        @endif
    </select>
    <p class="text-xs text-gray-500 mt-1" id="teacher-hint">
        Pilih paket terlebih dahulu untuk melihat daftar guru.
    </p>
    @error('assigned_teacher_id')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>

        {{-- Ruangan --}}
        <div>
            <label class="block text-sm font-medium">Ruangan Default</label>
            <select name="assigned_room_id"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                <option value="">— Belum ditentukan —</option>
                @foreach($rooms as $r)
                    <option value="{{ $r->id }}"
                        {{ old('assigned_room_id', $student?->assigned_room_id) == $r->id ? 'selected' : '' }}>
                        [{{ $r->code }}] {{ $r->name }}
                    </option>
                @endforeach
            </select>
            @error('assigned_room_id')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Hari Preferensi --}}
        <div>
            <label class="block text-sm font-medium">Hari Preferensi</label>
            <select name="preferred_day"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                <option value="">— Tidak dipilih —</option>
                @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $d)
                    <option value="{{ $d }}"
                        {{ old('preferred_day', $student?->preferred_day) == $d ? 'selected' : '' }}>
                        {{ $d }}
                    </option>
                @endforeach
            </select>
            @error('preferred_day')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Jam Preferensi --}}
        <div>
            <label class="block text-sm font-medium">Jam Preferensi</label>
            <input type="time" name="preferred_time"
                   value="{{ old('preferred_time', $student?->preferred_time ? \Carbon\Carbon::parse($student->preferred_time)->format('H:i') : '') }}"
                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            @error('preferred_time')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

    </div>
</fieldset>

{{-- ============= JAVASCRIPT: Conditional Fields ============= --}}
<script>
function handleStatusChange(status) {
    const trialField = document.getElementById('field-trial-date');
    const skipField = document.getElementById('field-skip-reason');

    // Guard: element mungkin tidak ada di mode edit
    if (trialField) trialField.style.display = 'none';
    if (skipField) skipField.style.display = 'none';

    if (status === 'Trial') {
        if (trialField) trialField.style.display = 'block';
    } else if (status === 'Aktif') {
        if (skipField) skipField.style.display = 'block';
    }
}

// Jalankan saat halaman load (kalau ada old value setelah validation error)
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status-select');
    if (statusSelect && statusSelect.value) {
        handleStatusChange(statusSelect.value);
    }
});

// ============= Filter Guru by Instrumen =============
function handlePackageChange(selectEl) {
    const instrumentId = selectEl.options[selectEl.selectedIndex].dataset.instrumentId;
    const teacherSelect = document.getElementById('teacher-select');
    const teacherHint = document.getElementById('teacher-hint');

    // Reset guru dropdown
    teacherSelect.innerHTML = '';

    // Kalau tidak ada instrumen (pilihan kosong)
    if (!instrumentId) {
        teacherSelect.innerHTML = '<option value="">— Pilih paket dulu —</option>';
        teacherHint.textContent = 'Pilih paket terlebih dahulu untuk melihat daftar guru.';

        // Trigger status change juga (kalau ada)
        handleStatusChange(document.getElementById('status-select')?.value || '');
        return;
    }

    // Loading state
    teacherSelect.innerHTML = '<option value="">Memuat daftar guru...</option>';
    teacherHint.textContent = 'Sedang memuat...';

    // AJAX ke server
    fetch(`/api/teachers-by-instrument/${instrumentId}`)
        .then(response => response.json())
        .then(teachers => {
            teacherSelect.innerHTML = '';

            if (teachers.length === 0) {
                teacherSelect.innerHTML = '<option value="">Tidak ada guru untuk instrumen ini</option>';
                teacherHint.textContent = 'Tidak ada guru aktif untuk instrumen ini.';
                return;
            }

            // Tambah opsi default
            teacherSelect.innerHTML = '<option value="">— Pilih Guru —</option>';

            // Tambah opsi guru
            teachers.forEach(teacher => {
                const option = document.createElement('option');
                option.value = teacher.id;
                option.textContent = `[${teacher.code}] ${teacher.name}`;

                // Pre-select kalau ada current value (mode edit / old input)
                const currentTeacherId = '{{ $student?->assigned_teacher_id ?? '' }}';
                const oldTeacherId = '{{ old('assigned_teacher_id', '') }}';
                if (option.value == oldTeacherId || option.value == currentTeacherId) {
                    option.selected = true;
                }

                teacherSelect.appendChild(option);
            });

            teacherHint.textContent = `${teachers.length} guru tersedia untuk instrumen ini.`;
        })
        .catch(error => {
            teacherSelect.innerHTML = '<option value="">Error memuat guru</option>';
            teacherHint.textContent = 'Gagal memuat daftar guru. Refresh halaman.';
            console.error('Error:', error);
        });
}

// Auto-trigger saat halaman load (untuk mode edit atau old input)
document.addEventListener('DOMContentLoaded', function() {
    const packageSelect = document.getElementById('package-select');
    if (packageSelect && packageSelect.value) {
        handlePackageChange(packageSelect);
    }

    // Juga trigger status change kalau ada old value
    const statusSelect = document.getElementById('status-select');
    if (statusSelect && statusSelect.value) {
        handleStatusChange(statusSelect.value);
    }
});

</script>
