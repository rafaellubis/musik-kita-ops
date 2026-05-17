<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Edit Komponen Honor</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $honor->slip_number }} · {{ $honor->teacher->name }} · {{ $monthName }}
                </div>
            </div>
            <a href="{{ route('honors.show', $honor) }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6 max-w-2xl">

            <div class="mb-5 p-3 bg-gray-50 rounded text-sm text-gray-600">
                <div class="font-medium">{{ $honor->slip_number }} · {{ $monthName }}</div>
                <div class="mt-1">
                    Honor pokok (otomatis): <strong>Rp {{ number_format($honor->base_honor, 0, ',', '.') }}</strong>
                </div>
                <div class="text-xs text-gray-400 mt-1">
                    Honor pokok tidak bisa diubah di sini — berasal dari data absensi.
                    Untuk mengubahnya, perbaiki data absensi lalu jalankan ulang kalkulasi.
                </div>
            </div>

            <form method="POST" action="{{ route('honors.update', $honor) }}">
                @csrf
                @method('PATCH')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">
                        Honor Transport (Rp)
                        <span class="text-gray-400 font-normal text-xs ml-1">— input manual, isi 0 jika tidak ada</span>
                    </label>
                    <input type="number"
                           name="transport_honor"
                           value="{{ old('transport_honor', $honor->transport_honor) }}"
                           min="0" max="99999999" required
                           class="mt-1 block w-full border-gray-300 rounded @error('transport_honor') border-red-500 @enderror">
                    @error('transport_honor')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">
                        Honor Lain-lain (Rp)
                        <span class="text-gray-400 font-normal text-xs ml-1">— input manual, isi 0 jika tidak ada</span>
                    </label>
                    <input type="number"
                           name="other_honor"
                           id="other_honor"
                           value="{{ old('other_honor', $honor->other_honor) }}"
                           min="0" max="99999999" required
                           class="mt-1 block w-full border-gray-300 rounded @error('other_honor') border-red-500 @enderror"
                           oninput="toggleNote()">
                    @error('other_honor')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5" id="note_wrapper">
                    <label class="block text-sm font-medium text-gray-700">
                        Keterangan Lain-lain
                        <span class="text-red-500">*</span>
                        <span class="text-gray-400 font-normal text-xs ml-1">— wajib diisi jika ada honor lain-lain</span>
                    </label>
                    <input type="text"
                           name="other_honor_note"
                           value="{{ old('other_honor_note', $honor->other_honor_note) }}"
                           maxlength="255"
                           placeholder="Contoh: bonus akhir tahun, biaya event khusus"
                           class="mt-1 block w-full border-gray-300 rounded @error('other_honor_note') border-red-500 @enderror">
                    @error('other_honor_note')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Preview total --}}
                <div class="mb-5 p-3 bg-blue-50 rounded text-sm">
                    <div class="text-xs text-gray-500 mb-1">Preview Total Honor</div>
                    <div class="font-bold text-blue-700">
                        Rp {{ number_format($honor->base_honor, 0, ',', '.') }}
                        + <span id="preview_transport">Rp {{ number_format($honor->transport_honor, 0, ',', '.') }}</span>
                        + <span id="preview_other">Rp {{ number_format($honor->other_honor, 0, ',', '.') }}</span>
                        = <span id="preview_total" class="text-lg">Rp {{ number_format($honor->total_honor, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="{{ route('honors.show', $honor) }}"
                       class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-bold transition-colors btn-mk-primary"
                            >
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const baseHonor = {{ $honor->base_honor }};

        function toggleNote() {
            // selalu tampil
        }

        document.addEventListener('input', function () {
            const transport = parseInt(document.querySelector('[name=transport_honor]')?.value) || 0;
            const other     = parseInt(document.querySelector('[name=other_honor]')?.value) || 0;
            const total     = baseHonor + transport + other;

            const fmt = (n) => 'Rp ' + n.toLocaleString('id-ID');
            const el  = (id) => document.getElementById(id);

            if (el('preview_transport')) el('preview_transport').textContent = fmt(transport);
            if (el('preview_other'))     el('preview_other').textContent     = fmt(other);
            if (el('preview_total'))     el('preview_total').textContent     = fmt(total);
        });
    </script>
</x-app-layout>
