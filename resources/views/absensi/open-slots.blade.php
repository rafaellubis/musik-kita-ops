<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text leading-tight">
            Open Slot Board — Sesi IZIN_PENDING
        </h2>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">

        {{-- Penjelasan singkat --}}
        <div class="mb-5 bg-mk-card border border-mk-border rounded-lg px-4 py-3 text-sm text-mk-muted">
            Daftar sesi dengan status <span class="font-semibold text-yellow-600">IZIN_PENDING</span>
            yang belum memiliki sesi pengganti. Admin bisa <strong>isi slot</strong> dengan murid lain
            (slot tetap pending untuk murid asli), atau <strong>jadwalkan pengganti</strong> untuk murid asli.
        </div>

        {{-- Flash success / error --}}
        <div id="flash-msg" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-medium"></div>

        @if($slots->isEmpty())
            <div class="text-center py-12 bg-mk-card border border-mk-border rounded-lg shadow-sm">
                <p class="text-gray-500 text-sm">Tidak ada slot terbuka saat ini.</p>
            </div>
        @else
            <div class="bg-mk-card shadow-sm rounded-lg overflow-x-auto">
                <table class="w-full text-sm divide-y divide-mk-border">
                    <thead class="bg-mk-surface">
                        <tr class="text-mk-dim font-medium text-xs uppercase tracking-wider">
                            <th class="px-4 py-3 text-left rounded-tl-lg">Tanggal & Jam</th>
                            <th class="px-4 py-3 text-left">Guru</th>
                            <th class="px-4 py-3 text-left">Ruang</th>
                            <th class="px-4 py-3 text-left">Murid Asli</th>
                            <th class="px-4 py-3 text-center">Sesi ke-</th>
                            <th class="px-4 py-3 text-center">Pending sejak</th>
                            <th class="px-4 py-3 text-right rounded-tr-lg">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mk-border">
                        @foreach($slots as $slot)
                            {{-- Baris utama slot --}}
                            <tr class="hover:bg-mk-surface transition-colors"
                                x-data="{
                                    showAction: null,
                                    loading: false,
                                    errorMsg: '',
                                    successMsg: '',
                                    // Form isi slot (murid lain)
                                    isiEnrollmentId: '',
                                    isiRoomId: '',
                                    // Form jadwalkan pengganti (murid asli)
                                    jadwalDate: '',
                                    jadwalTime: '',
                                    jadwalRoomId: '',
                                    async postAction(url, payload) {
                                        this.loading  = true;
                                        this.errorMsg = '';
                                        try {
                                            const res = await fetch(url, {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                    'Accept': 'application/json',
                                                },
                                                body: JSON.stringify(payload),
                                            });
                                            const data = await res.json();
                                            if (data.success) {
                                                showFlash(data.message, 'success');
                                                this.$el.closest('tr').remove();
                                            } else {
                                                this.errorMsg = data.message || 'Gagal menyimpan.';
                                            }
                                        } catch (e) {
                                            this.errorMsg = 'Terjadi kesalahan koneksi.';
                                        } finally {
                                            this.loading = false;
                                        }
                                    },
                                    submitIsiSlot() {
                                        if (!this.isiEnrollmentId) { this.errorMsg = 'Pilih murid terlebih dahulu.'; return; }
                                        this.postAction(
                                            '{{ route('absensi.open-slots.assign', $slot) }}',
                                            { enrollment_id: this.isiEnrollmentId, room_id: this.isiRoomId || null }
                                        );
                                    },
                                    submitJadwal() {
                                        if (!this.jadwalDate || !this.jadwalTime) { this.errorMsg = 'Tanggal dan jam wajib diisi.'; return; }
                                        this.postAction(
                                            '{{ route('absensi.open-slots.schedule', $slot) }}',
                                            { replacement_date: this.jadwalDate, replacement_time: this.jadwalTime, room_id: this.jadwalRoomId || null }
                                        );
                                    },
                                }">

                                {{-- Kolom: Tanggal & Jam --}}
                                <td class="px-4 py-3 text-mk-text font-medium whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($slot->session_date)->translatedFormat('d M Y') }}
                                    <span class="block text-xs text-mk-muted">
                                        {{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}
                                    </span>
                                </td>

                                {{-- Kolom: Guru --}}
                                <td class="px-4 py-3 text-mk-text">
                                    {{ $slot->teacher->name ?? '—' }}
                                </td>

                                {{-- Kolom: Ruang --}}
                                <td class="px-4 py-3 text-mk-muted">
                                    {{ $slot->room->code ?? '—' }}
                                </td>

                                {{-- Kolom: Murid Asli --}}
                                <td class="px-4 py-3">
                                    <span class="text-mk-text font-medium">{{ $slot->student->full_name ?? '—' }}</span>
                                    <span class="block text-xs text-mk-dim">{{ $slot->enrollment->package->code ?? '' }}</span>
                                </td>

                                {{-- Kolom: Sesi ke- --}}
                                <td class="px-4 py-3 text-center text-mk-muted">
                                    {{ $slot->session_sequence ?? '—' }}
                                </td>

                                {{-- Kolom: Pending sejak (berapa hari lalu) --}}
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $hariLalu = \Carbon\Carbon::parse($slot->session_date)->diffInDays(now(), false);
                                    @endphp
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                        {{ $hariLalu >= 7 ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700' }}">
                                        {{ $hariLalu }} hari
                                    </span>
                                </td>

                                {{-- Kolom: Aksi --}}
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        @click="showAction = (showAction === 'isi') ? null : 'isi'"
                                        class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded border
                                               border-mk-border text-mk-muted hover:text-mk-text hover:bg-mk-surface
                                               transition-colors mr-1">
                                        Isi Slot
                                    </button>
                                    <button
                                        @click="showAction = (showAction === 'jadwal') ? null : 'jadwal'"
                                        class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded border
                                               border-mk-border text-mk-muted hover:text-mk-text hover:bg-mk-surface
                                               transition-colors">
                                        Jadwalkan Pengganti
                                    </button>
                                </td>
                            </tr>

                            {{-- Baris expand: form Isi Slot --}}
                            <tr x-show="showAction === 'isi'" x-cloak class="bg-mk-surface">
                                <td colspan="7" class="px-6 py-4">
                                    <p class="text-xs font-semibold text-mk-muted mb-3 uppercase tracking-wider">
                                        Isi slot dengan murid lain (sesi IZIN_PENDING murid asli tetap pending)
                                    </p>
                                    <div class="flex flex-wrap items-end gap-3">
                                        {{-- Pilih enrollment murid lain --}}
                                        <div>
                                            <label class="block text-xs text-mk-dim mb-1">Murid (Enrollment)</label>
                                            <select x-model="isiEnrollmentId"
                                                class="border border-gray-300 rounded text-sm px-3 py-1.5 min-w-[200px]">
                                                <option value="">— Pilih murid —</option>
                                                {{-- $enrollments sudah di-load di controller — tidak ada query di sini --}}
                                                @foreach($enrollments as $enr)
                                                    <option value="{{ $enr->id }}">
                                                        {{ $enr->student->full_name ?? '?' }} — {{ $enr->package->code ?? '?' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Ruang (opsional) --}}
                                        <div>
                                            <label class="block text-xs text-mk-dim mb-1">Ruang (opsional)</label>
                                            <select x-model="isiRoomId"
                                                class="border border-gray-300 rounded text-sm px-3 py-1.5">
                                                <option value="">— Tanpa ruang —</option>
                                                @foreach($rooms as $r)
                                                    <option value="{{ $r->id }}">{{ $r->code }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Tombol konfirmasi --}}
                                        <button @click="submitIsiSlot()"
                                            :disabled="loading"
                                            class="px-4 py-1.5 rounded text-sm font-medium bg-yellow-500 text-white
                                                   hover:bg-yellow-600 disabled:opacity-50 transition-colors">
                                            <span x-show="!loading">Konfirmasi Isi Slot</span>
                                            <span x-show="loading">Menyimpan...</span>
                                        </button>

                                        <button @click="showAction = null"
                                            class="px-3 py-1.5 rounded text-sm text-mk-muted hover:text-mk-text transition-colors">
                                            Batal
                                        </button>
                                    </div>

                                    {{-- Pesan error --}}
                                    <p x-show="errorMsg" x-text="errorMsg"
                                        class="mt-2 text-xs text-red-600"></p>
                                </td>
                            </tr>

                            {{-- Baris expand: form Jadwalkan Pengganti --}}
                            <tr x-show="showAction === 'jadwal'" x-cloak class="bg-mk-surface">
                                <td colspan="7" class="px-6 py-4">
                                    <p class="text-xs font-semibold text-mk-muted mb-3 uppercase tracking-wider">
                                        Jadwalkan sesi pengganti untuk murid asli
                                    </p>
                                    <div class="flex flex-wrap items-end gap-3">
                                        {{-- Tanggal pengganti --}}
                                        <div>
                                            <label class="block text-xs text-mk-dim mb-1">Tanggal</label>
                                            <input type="date" x-model="jadwalDate"
                                                class="border border-gray-300 rounded text-sm px-3 py-1.5">
                                        </div>

                                        {{-- Jam mulai --}}
                                        <div>
                                            <label class="block text-xs text-mk-dim mb-1">Jam Mulai</label>
                                            <input type="time" x-model="jadwalTime"
                                                class="border border-gray-300 rounded text-sm px-3 py-1.5">
                                        </div>

                                        {{-- Ruang (opsional) --}}
                                        <div>
                                            <label class="block text-xs text-mk-dim mb-1">Ruang (opsional)</label>
                                            <select x-model="jadwalRoomId"
                                                class="border border-gray-300 rounded text-sm px-3 py-1.5">
                                                <option value="">— Tanpa ruang —</option>
                                                @foreach($rooms as $r)
                                                    <option value="{{ $r->id }}">{{ $r->code }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Tombol konfirmasi --}}
                                        <button @click="submitJadwal()"
                                            :disabled="loading"
                                            class="px-4 py-1.5 rounded text-sm font-medium bg-green-600 text-white
                                                   hover:bg-green-700 disabled:opacity-50 transition-colors">
                                            <span x-show="!loading">Jadwalkan</span>
                                            <span x-show="loading">Menyimpan...</span>
                                        </button>

                                        <button @click="showAction = null"
                                            class="px-3 py-1.5 rounded text-sm text-mk-muted hover:text-mk-text transition-colors">
                                            Batal
                                        </button>
                                    </div>

                                    {{-- Pesan error --}}
                                    <p x-show="errorMsg" x-text="errorMsg"
                                        class="mt-2 text-xs text-red-600"></p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>

    {{-- Flash message helper (muncul di atas halaman) --}}
    <script>
        function showFlash(msg, type) {
            const el = document.getElementById('flash-msg');
            el.textContent = msg;
            el.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium '
                + (type === 'success'
                    ? 'bg-green-100 text-green-800 border border-green-200'
                    : 'bg-red-100 text-red-800 border border-red-200');
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 4000);
        }
    </script>
</x-app-layout>
