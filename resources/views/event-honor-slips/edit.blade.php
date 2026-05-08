<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl">Edit Slip Honor Event</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $slip->slip_number }} · {{ $slip->teacher->name }} · {{ $slip->event->name }}
                </p>
            </div>
            <a href="{{ route('events.show', $slip->event) }}" class="text-sm text-gray-600 hover:underline">← Kembali</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-lg mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <div class="mb-5 p-3 bg-blue-50 rounded text-xs text-blue-700">
                    <strong>Event:</strong> {{ $slip->event->name }}
                    ({{ $slip->event->event_date->format('d M Y') }})<br>
                    <strong>Guru:</strong> {{ $slip->teacher->name }}
                </div>

                <form method="POST" action="{{ route('event-honor-slips.update', $slip) }}">
                    @csrf @method('PATCH')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Peran / Keterangan</label>
                        <input type="text" name="role" maxlength="100"
                               value="{{ old('role', $slip->role) }}"
                               placeholder="Pengawas Ujian"
                               class="mt-1 block w-full border-gray-300 rounded text-sm">
                        <p class="mt-1 text-xs text-gray-400">Contoh: Pengawas Ujian, Pelatih Piano, MC Concert</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Honor Pokok (Rp) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="base_honor" required min="0" max="9999999"
                               value="{{ old('base_honor', $slip->base_honor) }}"
                               class="mt-1 block w-full border-gray-300 rounded @error('base_honor') border-red-500 @enderror">
                        @error('base_honor')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-gray-400">H_UJIAN default Rp 250.000 flat per event</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Honor Transport (Rp) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="transport_honor" required min="0" max="9999999"
                               value="{{ old('transport_honor', $slip->transport_honor) }}"
                               class="mt-1 block w-full border-gray-300 rounded @error('transport_honor') border-red-500 @enderror">
                        @error('transport_honor')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-gray-400">Input manual. Isi 0 jika tidak ada.</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Honor Lain-lain (Rp) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="other_honor" required min="0" max="9999999"
                               value="{{ old('other_honor', $slip->other_honor) }}"
                               class="mt-1 block w-full border-gray-300 rounded @error('other_honor') border-red-500 @enderror">
                        @error('other_honor')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700">
                            Keterangan Lain-lain
                            <span class="text-xs font-normal text-gray-400">— wajib jika ada honor lain-lain</span>
                        </label>
                        <input type="text" name="other_honor_note" maxlength="255"
                               value="{{ old('other_honor_note', $slip->other_honor_note) }}"
                               placeholder="Contoh: Bonus koordinasi event"
                               class="mt-1 block w-full border-gray-300 rounded text-sm @error('other_honor_note') border-red-500 @enderror">
                        @error('other_honor_note')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    {{-- Ringkasan total sementara --}}
                    <div class="mb-5 p-3 bg-gray-50 rounded text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Honor Pokok</span>
                            <span class="font-mono" id="preview-base">
                                Rp {{ number_format($slip->base_honor, 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Transport</span>
                            <span class="font-mono" id="preview-transport">
                                Rp {{ number_format($slip->transport_honor, 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Lain-lain</span>
                            <span class="font-mono" id="preview-other">
                                Rp {{ number_format($slip->other_honor, 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex justify-between font-semibold border-t mt-2 pt-2">
                            <span>Total Honor</span>
                            <span class="font-mono" id="preview-total">
                                Rp {{ number_format($slip->total_honor, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                            Simpan
                        </button>
                        <a href="{{ route('events.show', $slip->event) }}"
                           class="px-5 py-2 text-gray-600 hover:text-gray-800 text-sm">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Preview total saat input berubah
    const fmtRp = n => 'Rp ' + n.toLocaleString('id-ID');
    document.querySelectorAll('[name=base_honor],[name=transport_honor],[name=other_honor]')
        .forEach(el => el.addEventListener('input', () => {
            const b = parseInt(document.querySelector('[name=base_honor]').value) || 0;
            const t = parseInt(document.querySelector('[name=transport_honor]').value) || 0;
            const o = parseInt(document.querySelector('[name=other_honor]').value) || 0;
            document.getElementById('preview-base').textContent = fmtRp(b);
            document.getElementById('preview-transport').textContent = fmtRp(t);
            document.getElementById('preview-other').textContent = fmtRp(o);
            document.getElementById('preview-total').textContent = fmtRp(b + t + o);
        }));
    </script>
</x-app-layout>
