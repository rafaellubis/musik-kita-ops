<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block text-xs text-mk-dim mb-1">Nama Lengkap *</label>
        <input type="text" name="full_name" value="{{ old('full_name', $employee->full_name ?? '') }}"
               class="w-full border-mk-border rounded text-sm" required>
        @error('full_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-xs text-mk-dim mb-1">Posisi / Jabatan *</label>
        <input type="text" name="position" value="{{ old('position', $employee->position ?? '') }}"
               placeholder="Admin Operasional, Cleaning, dll."
               class="w-full border-mk-border rounded text-sm" required>
        @error('position')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-xs text-mk-dim mb-1">Gaji Pokok / Bulan (Rp) *</label>
        <input type="number" name="base_salary" min="0" step="1000"
               value="{{ old('base_salary', $employee->base_salary ?? 0) }}"
               class="w-full border-mk-border rounded text-sm" required>
        @error('base_salary')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs text-mk-dim mb-1">Link ke User Login (opsional)</label>
        <select name="user_id" class="w-full border-mk-border rounded text-sm">
            <option value="">— Tidak ada login —</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}"
                    {{ (string) old('user_id', $employee->user_id ?? '') === (string) $u->id ? 'selected' : '' }}>
                    {{ $u->name }} ({{ $u->email }})
                </option>
            @endforeach
        </select>
        <p class="text-xs text-mk-dim mt-1">Hanya Owner/Admin yang belum terhubung ke karyawan lain.</p>
        @error('user_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-xs text-mk-dim mb-1">Bank</label>
        <input type="text" name="bank_name" value="{{ old('bank_name', $employee->bank_name ?? '') }}"
               class="w-full border-mk-border rounded text-sm">
    </div>
    <div>
        <label class="block text-xs text-mk-dim mb-1">No. Rekening</label>
        <input type="text" name="bank_account" value="{{ old('bank_account', $employee->bank_account ?? '') }}"
               class="w-full border-mk-border rounded text-sm">
    </div>
    <div class="md:col-span-2">
        <label class="block text-xs text-mk-dim mb-1">Atas Nama Rekening</label>
        <input type="text" name="bank_account_holder"
               value="{{ old('bank_account_holder', $employee->bank_account_holder ?? '') }}"
               class="w-full border-mk-border rounded text-sm">
    </div>

    <div>
        <label class="block text-xs text-mk-dim mb-1">Tanggal Bergabung</label>
        <input type="date" name="joined_date"
               value="{{ old('joined_date', isset($employee) && $employee->joined_date ? $employee->joined_date->format('Y-m-d') : '') }}"
               class="w-full border-mk-border rounded text-sm">
    </div>

    <div class="flex items-center gap-2 pt-6">
        <input type="checkbox" name="is_active" id="is_active" value="1"
               {{ old('is_active', $employee->is_active ?? true) ? 'checked' : '' }}
               class="rounded border-mk-border">
        <label for="is_active" class="text-sm">Karyawan Aktif</label>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs text-mk-dim mb-1">Catatan</label>
        <textarea name="notes" rows="3"
                  class="w-full border-mk-border rounded text-sm">{{ old('notes', $employee->notes ?? '') }}</textarea>
    </div>
</div>
