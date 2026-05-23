@php $holiday = $holiday ?? null; @endphp

@if($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded">
        <ul class="text-sm text-red-700 list-disc pl-5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

{{-- Alpine.js component untuk auto-suggest replacement_date --}}
<div x-data="{
    holidayDate: '{{ old('date', $holiday?->date?->format('Y-m-d') ?? '') }}',
    type: '{{ old('type', $holiday->type ?? '') }}',
    replacementDate: '{{ old('replacement_date', $holiday?->replacement_date?->format('Y-m-d') ?? '') }}',
    suggestion: '',
    isInternal: false,

    get replacementDisabled() {
        return this.type === 'Internal';
    },

    computeSuggestion() {
        this.isInternal = this.type === 'Internal';
        if (this.isInternal) {
            this.suggestion = '';
            return;
        }
        if (!this.holidayDate) { this.suggestion = ''; return; }

        const d   = new Date(this.holidayDate);
        const dow = d.getDay(); // 0=Minggu, 1=Senin, ..., 6=Sabtu
        const y   = d.getFullYear();
        const m   = d.getMonth(); // 0-indexed

        // Cari semua tanggal dengan day-of-week yang sama di bulan ini
        const occurrences = [];
        const daysInMonth = new Date(y, m + 1, 0).getDate();
        for (let day = 1; day <= daysInMonth; day++) {
            const candidate = new Date(y, m, day);
            if (candidate.getDay() === dow) occurrences.push(candidate);
        }

        if (occurrences.length < 5) {
            this.suggestion = 'Tidak ada minggu ke-5 di bulan ini';
        } else {
            const week5 = occurrences[4];
            const fmt   = week5.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            const ymd   = week5.toISOString().split('T')[0];
            this.suggestion = 'Saran: ' + fmt + ' (' + ymd + ')';
            this._suggestedDate = ymd;
        }
    },

    useSuggestion() {
        if (this._suggestedDate) this.replacementDate = this._suggestedDate;
    }
}" x-init="computeSuggestion()">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Tanggal Libur --}}
        <div>
            <label class="block text-sm font-medium">Tanggal <span class="text-red-500">*</span></label>
            <input type="date" name="date" required
                   x-model="holidayDate"
                   @change="computeSuggestion()"
                   class="mt-1 block w-full border-gray-300 rounded-md">
        </div>

        {{-- Tipe --}}
        <div>
            <label class="block text-sm font-medium">Tipe <span class="text-red-500">*</span></label>
            <select name="type" required
                    x-model="type"
                    @change="computeSuggestion()"
                    class="mt-1 block w-full border-gray-300 rounded-md">
                @php $types = ['Nasional', 'Cuti Bersama', 'Internal']; @endphp
                <option value="">— Pilih —</option>
                @foreach($types as $t)
                    <option value="{{ $t }}"
                        {{ old('type', $holiday->type ?? '') == $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
        </div>

        {{-- Nama Hari Libur --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Nama Hari Libur <span class="text-red-500">*</span></label>
            <input type="text" name="name" required maxlength="100"
                   value="{{ old('name', $holiday->name ?? '') }}"
                   class="mt-1 block w-full border-gray-300 rounded-md"
                   placeholder="Tahun Baru Masehi">
        </div>

        {{-- Tanggal Pengganti --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">
                Tanggal Pengganti
                <span class="text-gray-400 text-xs font-normal ml-1">(opsional — dalam bulan yang sama)</span>
            </label>

            <div class="mt-1 flex gap-2 items-start">
                <input type="date" name="replacement_date"
                       x-model="replacementDate"
                       :disabled="replacementDisabled"
                       :class="replacementDisabled ? 'opacity-40 cursor-not-allowed bg-gray-100' : ''"
                       class="block w-full border-gray-300 rounded-md">

                <button type="button"
                        x-show="!replacementDisabled && suggestion && suggestion.startsWith('Saran:')"
                        @click="useSuggestion()"
                        class="shrink-0 px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                    Pakai Saran
                </button>
            </div>

            {{-- Hint / suggestion --}}
            <p class="mt-1 text-xs"
               :class="replacementDisabled ? 'text-amber-600' : 'text-gray-500'"
               x-text="replacementDisabled
                   ? 'Event studio (Internal) — gunakan fitur Reschedule untuk sesi pengganti lintas bulan.'
                   : suggestion">
            </p>
        </div>

        {{-- Catatan --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium">Catatan</label>
            <textarea name="notes" rows="2"
                      class="mt-1 block w-full border-gray-300 rounded-md"
                      placeholder="Tanggal estimasi, perlu konfirmasi SKB Menteri">{{ old('notes', $holiday->notes ?? '') }}</textarea>
        </div>

        {{-- Aktif + Honor --}}
        <div class="flex items-center gap-6 flex-wrap">
            <label class="inline-flex items-center">
                <input type="checkbox" name="is_active" value="1"
                    {{ old('is_active', $holiday->is_active ?? true) ? 'checked' : '' }}
                    class="rounded border-gray-300">
                <span class="ml-2 text-sm">Aktif</span>
            </label>

            {{-- Internal: checkbox tampil & bisa diubah owner --}}
            <label class="inline-flex items-center" x-show="isInternal">
                <input type="checkbox" name="is_honor_paid" value="1"
                    {{ old('is_honor_paid', $holiday->is_honor_paid ?? false) ? 'checked' : '' }}
                    class="rounded border-gray-300">
                <span class="ml-2 text-sm">Guru mendapat honor saat libur ini</span>
            </label>

            {{-- Nasional / Cuti Bersama: honor selalu dibayar, tidak perlu checkbox --}}
            <span class="text-xs text-green-700 inline-flex items-center gap-1"
                  x-show="!isInternal && type !== ''">
                ✓ Honor guru selalu dibayar penuh (libur nasional)
            </span>
        </div>
    </div>
</div>