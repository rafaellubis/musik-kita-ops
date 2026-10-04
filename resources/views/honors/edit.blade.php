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
        <div class="bg-mk-card border border-mk-borderLight shadow-sm rounded-lg p-6 max-w-2xl">

            <div class="mb-5 p-4 bg-mk-surface border border-mk-borderLight rounded-lg text-sm text-mk-text">
                <div class="font-bold">{{ $honor->slip_number }} · {{ $monthName }}</div>
                <div class="mt-1.5 font-medium">
                    Honor pokok (otomatis): <strong class="text-mk-muted">Rp {{ number_format($honor->base_honor, 0, ',', '.') }}</strong>
                </div>
                <div class="text-xs text-mk-dim mt-1">
                    Honor pokok tidak bisa diubah di sini — berasal dari data absensi.
                    Untuk mengubahnya, perbaiki data absensi lalu jalankan ulang kalkulasi.
                </div>
            </div>

            <form method="POST" action="{{ route('honors.update', $honor) }}">
                @csrf
                @method('PATCH')

                <div class="mb-5 text-sm text-mk-text">
                    <h3 class="font-semibold">Honor Event (Otomatis)</h3>
                    <p class="mt-1 font-bold">Rp {{ number_format($honor->event_honor, 0, ',', '.') }}</p>
                    <p class="mt-1">{{ $honor->event_honor_note ?: 'Belum ada honor event yang diposting.' }}</p>
                    <p class="mt-1 text-mk-muted">Dihitung dari peserta saat event diselesaikan. Koreksi melalui event terkait.</p>
                    @include('honors.partials.event-entries')
                </div>

                <div class="mb-4">
                    <label for="transport_honor" class="block text-sm font-medium text-mk-muted">
                        Honor Transport (Rp)
                        <span class="text-mk-dim font-normal text-xs ml-1">— input manual, isi 0 jika tidak ada</span>
                    </label>
                    <input type="number"
                           name="transport_honor"
                           id="transport_honor"
                           value="{{ old('transport_honor', $honor->transport_honor) }}"
                           min="0" max="99999999" required
                           class="mt-1 block w-full border-mk-border rounded @error('transport_honor') border-red-500 @enderror">
                    @error('transport_honor')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="other_honor" class="block text-sm font-medium text-mk-muted">
                        Honor Lain-lain (Rp)
                        <span class="text-mk-dim font-normal text-xs ml-1">— input manual, isi 0 jika tidak ada</span>
                    </label>
                    <input type="number"
                           name="other_honor"
                           id="other_honor"
                           value="{{ old('other_honor', $honor->other_honor) }}"
                           min="0" max="99999999" required
                           class="mt-1 block w-full border-mk-border rounded @error('other_honor') border-red-500 @enderror">
                    @error('other_honor')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5" id="note_wrapper">
                    <label for="other_honor_note" class="block text-sm font-medium text-mk-muted">
                        Keterangan Lain-lain
                        <span class="text-red-500">*</span>
                        <span class="text-mk-dim font-normal text-xs ml-1">— wajib diisi jika ada honor lain-lain</span>
                    </label>
                    <input type="text"
                           name="other_honor_note"
                           id="other_honor_note"
                           value="{{ old('other_honor_note', $honor->other_honor_note) }}"
                           maxlength="255"
                           placeholder="Contoh: bonus akhir tahun, biaya event khusus"
                           class="mt-1 block w-full border-mk-border rounded @error('other_honor_note') border-red-500 @enderror">
                    @error('other_honor_note')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Preview total --}}
                <div class="mb-5 p-4 bg-mk-surface border border-mk-borderLight rounded-lg text-sm text-mk-text">
                    <div class="text-xs text-mk-dim mb-1 font-semibold uppercase tracking-wider">Preview Total Honor</div>
                    <div class="font-bold text-mk-muted text-base">
                        Rp {{ number_format($honor->base_honor, 0, ',', '.') }}
                        + <span id="preview_event" class="text-mk-text">Rp {{ number_format($honor->event_honor, 0, ',', '.') }}</span>
                        + <span id="preview_transport" class="text-mk-text">Rp {{ number_format($honor->transport_honor, 0, ',', '.') }}</span>
                        + <span id="preview_other" class="text-mk-text">Rp {{ number_format($honor->other_honor, 0, ',', '.') }}</span>
                        = <span id="preview_total" class="text-xl text-mk-accent font-extrabold block sm:inline mt-1 sm:mt-0">Rp {{ number_format($honor->total_honor, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="{{ route('honors.show', $honor) }}"
                       class="px-4 py-2 border border-mk-border text-mk-muted hover:bg-mk-surface hover:text-mk-text rounded-md text-sm font-semibold transition-colors">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded-md text-sm font-bold transition-colors btn-mk-primary"
                            >
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const baseHonor = {{ $honor->base_honor }};

        document.addEventListener('input', function () {
            const event     = {{ $honor->event_honor }};
            const transport = parseInt(document.querySelector('[name=transport_honor]')?.value) || 0;
            const other     = parseInt(document.querySelector('[name=other_honor]')?.value) || 0;
            const total     = baseHonor + event + transport + other;

            const fmt = (n) => 'Rp ' + n.toLocaleString('id-ID');
            const el  = (id) => document.getElementById(id);

            if (el('preview_event'))     el('preview_event').textContent     = fmt(event);
            if (el('preview_transport')) el('preview_transport').textContent = fmt(transport);
            if (el('preview_other'))     el('preview_other').textContent     = fmt(other);
            if (el('preview_total'))     el('preview_total').textContent     = fmt(total);
        });
    </script>
</x-app-layout>
