@php
    $isLibur = $session->status === 'LIBUR';

    // Ekstrak label replacement dari notes sesi asli (jika sudah pernah di-reschedule)
    $replacementLabel = '';
    if ($session->status === 'IZIN_RESCHEDULE' && $session->notes
        && str_starts_with($session->notes, 'Sesi pengganti: ')) {
        $replacementLabel = substr($session->notes, strlen('Sesi pengganti: '));
    }
@endphp

<tr class="hover:bg-gray-50 transition-colors"
    data-teacher-id="{{ $session->teacher_id }}"
    data-status="{{ $session->status }}"
    data-murid="{{ $session->student->full_name }}"
    @if(! $isLibur)
    x-data="{
        status: '{{ $session->status }}',
        lateMinutes: {{ $session->late_minutes ?? 15 }},
        substituteId: {{ $session->substitute_teacher_id ?? 'null' }},
        substituteLabel: @js($session->substituteTeacher?->name ?? ''),
        replacementLabel: @js($replacementLabel),
        rescheduleDate: '',
        rescheduleTime: '',
        rescheduleRoomId: null,
        showModal: null,
        loading: false,
        errorMsg: '',
        splitMode: false,
        part2Date: '',
        part2Time: '',
        part2RoomId: '',
        part2Error: '',
        async save(newStatus, extra = {}) {
            this.loading  = true;
            this.errorMsg = '';
            try {
                const res = await fetch('{{ route('absensi.update', $session) }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: newStatus, ...extra })
                });
                const data = await res.json();
                if (data.success) {
                    this.status = data.status;
                    if (data.late_minutes)            this.lateMinutes     = data.late_minutes;
                    if (data.substitute_teacher_name) this.substituteLabel = data.substitute_teacher_name;
                    if (data.replacement_label)       this.replacementLabel = data.replacement_label;
                    this.showModal = null;
                    this.$el.dataset.status = data.status;
                } else {
                    this.errorMsg = data.message || 'Gagal menyimpan.';
                }
            } finally { this.loading = false; }
        },
        saveReschedule() {
            if (!this.rescheduleDate || !this.rescheduleTime) return;
            this.save('IZIN_RESCHEDULE', {
                replacement_date:    this.rescheduleDate,
                replacement_time:    this.rescheduleTime,
                replacement_room_id: this.rescheduleRoomId || null,
            });
        },
        saveSplitPart1() {
            if (!this.rescheduleDate || !this.rescheduleTime) return;
            this.errorMsg = '';
            fetch('{{ route('absensi.split', ['classSession' => $session->id, 'part' => 1]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    replacement_date:    this.rescheduleDate,
                    replacement_time:    this.rescheduleTime,
                    replacement_room_id: this.rescheduleRoomId || null,
                }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.showModal = null;
                    this.splitMode = false;
                    window.location.reload();
                } else {
                    this.errorMsg = data.message;
                }
            })
            .catch(() => {
                this.errorMsg = 'Terjadi kesalahan. Coba lagi.';
            });
        },
        saveSplitPart2() {
            if (!this.part2Date || !this.part2Time) return;
            this.part2Error = '';
            fetch('{{ $session->origin_session_id ? route('absensi.split', ['classSession' => $session->origin_session_id, 'part' => 2]) : '' }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    replacement_date:    this.part2Date,
                    replacement_time:    this.part2Time,
                    replacement_room_id: this.part2RoomId || null,
                }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.showModal = null;
                    window.location.reload();
                } else {
                    this.part2Error = data.message;
                }
            })
            .catch(() => {
                this.part2Error = 'Terjadi kesalahan. Coba lagi.';
            });
        }
    }"
    :class="status !== 'SCHEDULED' ? 'opacity-60' : ''"
    @endif
>
    {{-- Jam --}}
    <td class="px-3 py-2.5 font-bold text-sm"
        @if(! $isLibur)
        :style="status === 'SCHEDULED' ? 'color:#D4A853;font-weight:700' : ''"
        :class="status !== 'SCHEDULED' ? 'text-gray-500' : ''"
        @else
        class="text-gray-500"
        @endif>
        {{ substr($session->start_time, 0, 5) }}
    </td>

    {{-- Murid --}}
    <td class="px-3 py-2.5 text-sm"
        @if(! $isLibur)
        :class="status === 'SCHEDULED' ? 'text-gray-800 font-medium' : 'text-gray-500'"
        @else
        class="text-gray-500"
        @endif>
        {{ $session->student->full_name }}
        @php
            $label       = $session->getSessionLabel();
            $instrumen   = $session->enrollment?->package?->instrument?->name;
            $durasiMenit = $session->enrollment?->package?->duration_min;
            $parts       = array_filter([
                $label !== '—' ? $label : null,
                $instrumen,
                $durasiMenit ? $durasiMenit . ' mnt' : null,
            ]);
        @endphp
        @if(count($parts))
            <div class="text-[11px] mt-0.5 {{ $session->origin_session_id ? 'text-blue-500' : 'text-gray-400' }}">
                {{ implode(' · ', $parts) }}
            </div>
        @endif
    </td>

    {{-- Guru --}}
    <td class="px-3 py-2.5 text-xs text-gray-500 text-center">{{ $session->teacher->name }}</td>

    {{-- Ruang --}}
    <td class="px-3 py-2.5 text-xs text-gray-500">{{ $session->room?->code ?? '—' }}</td>

    {{-- Aksi --}}
    <td class="px-3 py-2.5 text-right">

        @if($isLibur)
            <span class="bg-gray-100 text-gray-500 border border-gray-200 rounded px-3 py-1 text-xs">
                🗓 LIBUR
            </span>

        @else
            {{-- Tombol "Tambah Bagian 2": tampil hanya jika ini adalah sesi Split Part 1 dan Part 2 belum ada --}}
            @if($session->split_part === 1 && !isset($part2ExistsForOriginIds[$session->origin_session_id]))
                <button type="button"
                    @click="showModal = 'part2'"
                    class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs bg-purple-100 text-purple-700 hover:bg-purple-200 transition-colors mb-1">
                    + Tambah Bagian 2
                </button>
            @endif
            {{-- Badge setelah status diinput --}}
            <div x-show="status !== 'SCHEDULED'" class="flex items-center justify-end gap-2">
                <span class="rounded px-3 py-1 text-xs border"
                    :class="{
                        'bg-green-100 text-green-700 border-green-200':    status === 'HADIR',
                        'bg-orange-100 text-orange-700 border-orange-200': status === 'HADIR_TERLAMBAT',
                        'bg-red-100 text-red-700 border-red-200':          status === 'HANGUS',
                        'bg-yellow-100 text-yellow-700 border-yellow-200': status === 'IZIN_RESCHEDULE',
                        'bg-blue-100 text-blue-700 border-blue-200':       status === 'IZIN_VIDEO',
                        'bg-purple-100 text-purple-700 border-purple-200': status === 'DIGANTI',
                        'bg-gray-100 text-gray-400 border-gray-200':       status === 'CANCELLED',
                    }"
                    x-text="
                        status === 'HADIR'            ? '✓ HADIR' :
                        status === 'HADIR_TERLAMBAT'  ? '⏱ +' + lateMinutes + ' mnt' :
                        status === 'HANGUS'            ? '✕ HANGUS' :
                        status === 'IZIN_RESCHEDULE'   ? '📅 ' + (replacementLabel || 'IZIN') :
                        status === 'IZIN_VIDEO'        ? '📹 VIDEO' :
                        status === 'DIGANTI'           ? '↔ ' + substituteLabel :
                        status === 'CANCELLED'         ? '✕ BATAL' : status
                    ">
                </span>
                {{-- HADIR/HADIR_TERLAMBAT: hanya bisa di-batalkan, tidak bisa diubah ke status lain --}}
                <button x-show="status === 'HADIR' || status === 'HADIR_TERLAMBAT'"
                    @click="save('CANCELLED')"
                    :disabled="loading"
                    class="text-red-500 hover:text-red-700 text-xs underline">batalkan</button>
                {{-- Status lain (termasuk CANCELLED): bisa di-ubah ulang ke SCHEDULED.
                     Disembunyikan jika sesi sudah punya replacement — tidak boleh reschedule ulang. --}}
                @if(!isset($sessionIdsWithReplacement[$session->id]))
                <button x-show="status !== 'HADIR' && status !== 'HADIR_TERLAMBAT'"
                    @click="status = 'SCHEDULED'; errorMsg = ''"
                    class="text-gray-400 hover:text-gray-600 text-xs underline">ubah</button>
                @endif
            </div>

            {{-- Tombol aksi (status belum diinput) --}}
            <div x-show="status === 'SCHEDULED'"
                class="flex items-center justify-end gap-1.5"
                :class="loading ? 'opacity-50 pointer-events-none' : ''">

                <button @click="save('HADIR')"
                    class="rounded px-3 py-1.5 text-xs font-semibold btn-mk-primary">
                    HADIR
                </button>
                <button @click="save('HANGUS')"
                    class="border border-red-300 text-red-600 hover:bg-red-50 rounded px-3 py-1.5 text-xs">
                    HANGUS
                </button>
                {{-- IZIN → buka mini-modal. Jika sudah ada pengganti, pengganti lama di-cancel
                     oleh RescheduleService sebelum yang baru dibuat. --}}
                <button @click="showModal = 'reschedule'"
                    class="border border-yellow-300 text-yellow-700 hover:bg-yellow-50 rounded px-3 py-1.5 text-xs">
                    IZIN
                </button>
                <button @click="save('IZIN_VIDEO')"
                    class="border border-blue-300 text-blue-600 hover:bg-blue-50 rounded px-3 py-1.5 text-xs">
                    VIDEO
                </button>

                {{-- Tombol ··· dengan dropdown --}}
                <div class="relative">
                    <button @click="showModal = showModal === 'menu' ? null : 'menu'"
                        class="border border-gray-300 text-gray-600 hover:bg-gray-100 rounded px-2.5 py-1.5 text-xs">
                        ···
                    </button>

                    {{-- Dropdown menu --}}
                    <div x-show="showModal === 'menu'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-white border border-gray-200 rounded-lg shadow-lg w-36 py-1">
                        <button @click="showModal = 'terlambat'"
                            class="w-full text-left px-4 py-2 text-orange-600 text-xs hover:bg-gray-50">
                            Terlambat
                        </button>
                        <button @click="showModal = 'diganti'"
                            class="w-full text-left px-4 py-2 text-purple-600 text-xs hover:bg-gray-50">
                            Diganti
                        </button>
                    </div>

                    {{-- Mini-modal: TERLAMBAT --}}
                    <div x-show="showModal === 'terlambat'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-white border border-gray-200 rounded-lg shadow-lg w-56 p-4">
                        <p class="text-gray-500 text-xs mb-3 truncate">
                            {{ $session->student->full_name }} · {{ $session->teacher->name }}
                        </p>
                        <label class="block text-gray-500 text-xs mb-1">Terlambat berapa menit?</label>
                        <div class="flex items-center gap-2 mb-4">
                            <input type="number" x-model.number="lateMinutes" min="1" max="60"
                                class="border border-gray-300 text-gray-700 rounded px-3 py-1.5 w-20 text-center text-sm">
                            <span class="text-gray-500 text-xs">menit</span>
                        </div>
                        <div class="flex gap-2">
                            <button @click="save('HADIR_TERLAMBAT', { late_minutes: lateMinutes })"
                                class="flex-1 font-semibold text-xs py-2 rounded btn-mk-primary">
                                Simpan
                            </button>
                            <button @click="showModal = null"
                                class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs py-2 px-3 rounded">
                                Batal
                            </button>
                        </div>
                    </div>

                    {{-- Mini-modal: DIGANTI --}}
                    <div x-show="showModal === 'diganti'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-white border border-gray-200 rounded-lg shadow-lg w-64 p-4">
                        <p class="text-gray-500 text-xs mb-3 truncate">
                            {{ $session->student->full_name }} · {{ $session->teacher->name }}
                        </p>
                        <label class="block text-gray-500 text-xs mb-1">Guru pengganti</label>
                        <select x-model="substituteId"
                            class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-1">
                            <option value="">— Pilih guru pengganti —</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-gray-400 text-xs mb-3">Honor otomatis ke guru pengganti.</p>
                        <div class="flex gap-2">
                            <button @click="if(substituteId) save('DIGANTI', { substitute_teacher_id: substituteId })"
                                :disabled="!substituteId"
                                class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2 rounded btn-mk-primary">
                                Simpan
                            </button>
                            <button @click="showModal = null"
                                class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs py-2 px-3 rounded">
                                Batal
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Mini-modal: RESCHEDULE (di luar tombol ···, karena dipanggil dari tombol IZIN) --}}
            <div x-show="showModal === 'reschedule'" @click.outside="showModal = null"
                class="fixed inset-0 z-40 flex items-center justify-center"
                style="display: none;">
                <div class="bg-white border border-gray-200 rounded-lg shadow-xl w-80 p-5">
                    <p class="text-gray-700 text-sm font-medium mb-1">Jadwalkan Sesi Pengganti</p>
                    <p class="text-gray-400 text-xs mb-4 truncate">
                        {{ $session->student->full_name }} · {{ $session->teacher->name }}
                    </p>

                    {{-- Error message dari server (konflik guru/ruangan) --}}
                    <p x-show="errorMsg" x-text="errorMsg"
                        class="bg-red-50 border border-red-200 text-red-600 text-xs rounded px-3 py-2 mb-3">
                    </p>

                    <label class="block text-gray-500 text-xs mb-1">Tanggal Pengganti</label>
                    <input type="date" x-model="rescheduleDate"
                        class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-3">

                    <label class="block text-gray-500 text-xs mb-1">Jam Mulai</label>
                    <input type="time" x-model="rescheduleTime"
                        class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-3">

                    <label class="block text-gray-500 text-xs mb-1">Ruangan <span class="text-gray-400">(opsional)</span></label>
                    <select x-model="rescheduleRoomId"
                        class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-4">
                        <option value="">— Tanpa ruangan —</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>

                    {{-- Toggle: Bagi menjadi 2 bagian — hanya untuk sesi original (bukan hasil split) --}}
                    @if($session->split_part === null)
                    <div class="flex items-center gap-2 mb-3">
                        <button type="button"
                            @click="splitMode = !splitMode"
                            :class="splitMode ? 'bg-amber-600' : 'bg-gray-400'"
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none">
                            <span :class="splitMode ? 'translate-x-5' : 'translate-x-1'"
                                  class="inline-block h-3 w-3 transform rounded-full bg-white transition-transform"></span>
                        </button>
                        <span class="text-xs text-gray-600">Bagi menjadi 2 bagian</span>
                        <span x-show="splitMode" class="text-xs text-amber-600">(15 menit + 15 menit)</span>
                    </div>
                    @endif

                    <div class="flex gap-2">
                        <button type="button"
                            @click="{{ $session->split_part === null ? 'splitMode ? saveSplitPart1() : saveReschedule()' : 'saveReschedule()' }}"
                            :disabled="!rescheduleDate || !rescheduleTime"
                            class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2 rounded btn-mk-primary">
                            <span x-text="splitMode ? 'Jadwalkan Bagian 1' : 'Buat Sesi Pengganti'"></span>
                        </button>
                        <button @click="showModal = null; errorMsg = ''; splitMode = false"
                            class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs py-2 px-3 rounded">
                            Batal
                        </button>
                    </div>
                </div>
            </div>

            {{-- Modal: Jadwalkan Bagian 2 (muncul dari tombol "Tambah Bagian 2" pada baris Split Part 1) --}}
            <div x-show="showModal === 'part2'"
                 x-transition
                 class="fixed inset-0 z-40 flex items-center justify-center bg-black/50"
                 style="display: none;"
                 @click.self="showModal = null; part2Error = ''">
                <div class="bg-white border border-gray-200 rounded-lg shadow-xl w-80 p-5" @click.stop>
                    <p class="text-gray-700 text-sm font-medium mb-1">Jadwalkan Bagian 2</p>
                    <p class="text-gray-400 text-xs mb-4 truncate">
                        {{ $session->student->full_name }} · {{ $session->teacher->name }}
                        @php $lbl = $session->getSessionLabel(); @endphp
                        @if($lbl !== '—')
                            &mdash; {{ $lbl }}
                        @endif
                    </p>

                    {{-- Pesan error dari server --}}
                    <p x-show="part2Error" x-text="part2Error"
                        class="bg-red-50 border border-red-200 text-red-600 text-xs rounded px-3 py-2 mb-3">
                    </p>

                    <label class="block text-gray-500 text-xs mb-1">Tanggal</label>
                    <input type="date" x-model="part2Date"
                        class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-3">

                    <label class="block text-gray-500 text-xs mb-1">Jam Mulai</label>
                    <input type="time" x-model="part2Time"
                        class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-3">

                    <label class="block text-gray-500 text-xs mb-1">Ruangan <span class="text-gray-400">(opsional)</span></label>
                    <select x-model="part2RoomId"
                        class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-4">
                        <option value="">— Tanpa ruangan —</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>

                    <div class="flex gap-2">
                        <button type="button" @click="saveSplitPart2()"
                            :disabled="!part2Date || !part2Time"
                            class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2 rounded bg-purple-600 text-white hover:bg-purple-700">
                            Jadwalkan Bagian 2
                        </button>
                        <button type="button" @click="showModal = null; part2Error = ''"
                            class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs py-2 px-3 rounded">
                            Batal
                        </button>
                    </div>
                </div>
            </div>

        @endif
    </td>
</tr>
