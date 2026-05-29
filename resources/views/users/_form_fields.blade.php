{{--
    Partial: field form Tambah/Edit User
    $mode: 'create' atau 'edit'
    Depends on Alpine x-data: editUser, selectedRole, availableTeachers, availableTeachersForEdit()
--}}

{{-- Nama --}}
<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Nama Lengkap <span class="text-red-500">*</span>
    </label>
    @if($mode === 'create')
        <input type="text" name="name" value="{{ old('name') }}" required minlength="2" maxlength="100"
               placeholder="cth: Sari Andriani"
               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    @else
        <input type="text" name="name" :value="editUser.name" required minlength="2" maxlength="100"
               placeholder="cth: Sari Andriani"
               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    @endif
    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Username --}}
<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Username
        @if($mode === 'create')
            <span class="text-gray-400 font-normal normal-case">(opsional)</span>
        @else
            <span class="text-red-500">*</span>
        @endif
    </label>
    @if($mode === 'create')
        <input type="text" name="username" value="{{ old('username') }}"
               minlength="3" maxlength="30" pattern="[a-z0-9._-]+"
               placeholder="Otomatis dari nama jika dikosongkan"
               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
        <p class="text-xs text-gray-400 mt-1">Huruf kecil, angka, titik, strip, underscore. Min. 3 karakter.</p>
    @else
        <input type="text" name="username" :value="editUser.username" required
               minlength="3" maxlength="30" pattern="[a-z0-9._-]+"
               placeholder="cth: thomas"
               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    @endif
    @error('username') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Email --}}
<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Email <span class="text-red-500">*</span>
    </label>
    @if($mode === 'create')
        <input type="email" name="email" value="{{ old('email') }}" required
               placeholder="cth: sari@musikkita.local"
               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    @else
        <input type="email" name="email" :value="editUser.email" required
               placeholder="cth: sari@musikkita.local"
               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    @endif
    @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Role --}}
<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Role <span class="text-red-500">*</span>
    </label>
    <select name="role" required x-model="selectedRole"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
        <option value="">-- Pilih Role --</option>
        @foreach(['Owner','Admin','Auditor','Guru'] as $r)
        <option value="{{ $r }}">{{ $r }}</option>
        @endforeach
    </select>
    @error('role') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Teacher — hanya muncul jika role Guru --}}
<div class="mb-4 p-3 rounded-lg"
     style="background:rgba(58,125,68,0.06);border:1px solid rgba(58,125,68,0.2)"
     x-show="selectedRole === 'Guru'" x-cloak>
    <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#16a34a">
        👨‍🏫 Hubungkan ke Teacher <span class="text-red-500">*</span>
    </label>
    <select name="teacher_id" :required="selectedRole === 'Guru'"
            class="w-full border rounded-lg px-3 py-2 text-sm text-gray-900"
            style="border-color:rgba(58,125,68,0.35)">
        <option value="">-- Pilih Teacher --</option>
        @if($mode === 'create')
        <template x-for="t in availableTeachers" :key="t.id">
            <option :value="t.id" x-text="t.name"></option>
        </template>
        @else
        <template x-for="t in availableTeachersForEdit(editUser.id)" :key="t.id">
            <option :value="t.id" :selected="t.id === editUser.teacher_id" x-text="t.name"></option>
        </template>
        @endif
    </select>
    <p class="text-xs mt-1.5" style="color:#6B4A2A">
        @if($mode === 'create')
            Hanya Teacher yang belum punya akun yang ditampilkan.
        @else
            Teacher yang sudah punya akun lain tidak ditampilkan.
        @endif
    </p>
    @error('teacher_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Password — hanya saat Create --}}
@if($mode === 'create')
<div class="mb-2">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Password Awal <span class="text-red-500">*</span>
    </label>
    <input type="password" name="password" required minlength="8"
           placeholder="Min. 8 karakter"
           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    <p class="text-xs text-gray-400 mt-1">User bisa ganti password sendiri via halaman Profil.</p>
    @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>
@endif
