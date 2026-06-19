@php
    $isLibur = $session->status === 'LIBUR';

    // Ekstrak label replacement dari notes sesi asli (jika sudah pernah di-reschedule)
    $replacementLabel = '';
    if ($session->status === 'IZIN_RESCHEDULE' && $session->notes
        && str_starts_with($session->notes, 'Sesi pengganti: ')) {
        $replacementLabel = substr($session->notes, strlen('Sesi pengganti: '));
    }
@endphp

<tr class="hover:bg-mk-surface transition-colors"
    data-teacher-id="{{ $session->teacher_id }}"
    data-status="{{ $session->status }}"
    data-murid="{{ $session->student->full_name }}"
    @if(! $isLibur)
    x-data="{
        status: '{{ $session->status }}',
        dbStatus: '{{ $session->status }}',
        lateMinutes: {{ $session->late_minutes ?? 15 }},
        substituteId: {{ $session->substitute_teacher_id ?? 'null' }},
        substituteLabel: @js($session->substituteTeacher?->name ?? ''),
        substituteStartTime: '',
        substituteEndTime: '',
        substituteRoomId: null,
        honorConfirmed: {{ ($session->status === 'DIGANTI' && $session->honor_code !== null) ? 'true' : 'false' }},
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
        pendingNotes: '',
        async save(newStatus, extra = {}) {
            const oldStatus = this.status;
            // Optimistic update for simple immediate states (HADIR, HANGUS, IZIN_VIDEO, SCHEDULED)
            const isOptimistic = ['HADIR', 'HANGUS', 'IZIN_VIDEO', 'SCHEDULED'].includes(newStatus);
            if (isOptimistic) {
                this.status = newStatus;
            }
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
                    this.dbStatus = data.status;
                    if (data.late_minutes !== undefined) this.lateMinutes = data.late_minutes;
                    if (data.substitute_teacher_name) this.substituteLabel = data.substitute_teacher_name;
                    if (data.replacement_label)       this.replacementLabel = data.replacement_label;
                    if (newStatus === 'IZIN_PENDING') this.pendingNotes = '';
                    this.showModal = null;
                    this.$el.dataset.status = data.status;
                } else {
                    this.status = oldStatus; // rollback
                    this.errorMsg = data.message || 'Gagal menyimpan.';
                    alert(this.errorMsg);
                }
            } catch (err) {
                this.status = oldStatus; // rollback
                this.errorMsg = 'Koneksi terputus. Gagal menyimpan.';
                alert(this.errorMsg);
            } finally { this.loading = false; }
        },
        savePending() {
            this.save('IZIN_PENDING', {
                notes: this.pendingNotes.trim() || null,
            });
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
            this.loading = true;
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
                    this.loading = false;
                }
            })
            .catch(() => {
                this.errorMsg = 'Terjadi kesalahan. Coba lagi.';
                this.loading = false;
            });
        },
        saveSplitPart2() {
            if (!this.part2Date || !this.part2Time) return;
            this.part2Error = '';
            this.loading = true;
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
                    this.loading = false;
                }
            })
            .catch(() => {
                this.part2Error = 'Terjadi kesalahan. Coba lagi.';
                this.loading = false;
            });
        },
        async confirmSubstitute(action) {
            this.loading  = true;
            this.errorMsg = '';
            try {
                const res = await fetch('{{ route('absensi.confirm-substitute', $session) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ action }),
                });
                const data = await res.json();
                if (data.success) {
                    if (action === 'hadir') {
                        this.honorConfirmed = true;
                        this.$el.dataset.status = 'DIGANTI';
                    } else {
                        this.status = 'SCHEDULED';
                        this.dbStatus = 'SCHEDULED';
                        this.substituteId = null;
                        this.substituteLabel = '';
                        this.honorConfirmed = false;
                        this.$el.dataset.status = 'SCHEDULED';
                    }
                } else {
                    this.errorMsg = data.message || 'Gagal mengkonfirmasi.';
                }
            } finally { this.loading = false; }
        }
    }"
    :class="status !== 'SCHEDULED' ? 'opacity-60' : ''"
    @endif
>
    {{-- Jam --}}
    <td class="px-4 py-3 font-bold text-sm"
        @if(! $isLibur)
        :style="status === 'SCHEDULED' ? 'color:#5DB890;font-weight:700' : ''"
        :class="status !== 'SCHEDULED' ? 'text-mk-dim' : ''"
        @else
        class="text-mk-dim"
        @endif>
        {{ substr($session->start_time, 0, 5) }}
    </td>

    {{-- Murid --}}
    <td class="px-4 py-3 text-sm min-w-0 max-w-0"
        @if(! $isLibur)
        :class="status === 'SCHEDULED' ? 'text-mk-text font-medium' : 'text-mk-dim'"
        @else
        class="text-mk-dim"
        @endif>
        {{ $session->student->full_name }}
        @if($session->enrollment?->package?->isDuo())
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-800 ml-1">DUO</span>
        @endif
        @if($session->student->nickname)
            <div class="text-[12px] text-mk-dim mt-0.5">'{{ $session->student->nickname }}'</div>
        @endif
        @php
            $label       = $session->getSessionLabel();
            $instrumen   = $session->enrollment?->package?->instrument?->name;
            $durasiMenit = $session->enrollment?->package?->duration_min;
        @endphp
        @php
            $subParts = array_filter([
                $instrumen,
                $durasiMenit ? $durasiMenit . ' menit' : null,
            ]);
            $subInfo = count($subParts) ? '~ ' . implode(' . ', $subParts) : '';
        @endphp
        @if($label !== '—' || $subInfo)
            <div class="text-[11px] mt-0.5">
                @if($label !== '—')
                    <span class="{{ $session->origin_session_id ? 'text-blue-500' : 'text-mk-dim' }}">{{ $label }}</span>
                @endif
                @if($subInfo)
                    <span class="text-blue-500">{{ $subInfo }}</span>
                @endif
            </div>
        @endif
    </td>

    {{-- Guru --}}
    <td class="px-4 py-3 text-xs text-mk-dim text-left">{{ $session->teacher->name }}</td>

    {{-- Ruang --}}
    <td class="px-4 py-3 text-xs text-mk-dim">{{ $session->room?->code ?? '—' }}</td>

    {{-- Aksi --}}
    <td class="px-4 py-3 text-right">

        @if($isLibur)
            <span class="bg-mk-surface text-mk-dim border border-mk-border rounded px-3 py-1 text-xs">
                🗓 LIBUR
            </span>

        @else
            {{-- Tombol "Tambah Bagian 2": tampil hanya jika ini adalah sesi Split Part 1 dan Part 2 belum ada --}}
            @if($session->split_part === 1 && !isset($part2ExistsForOriginIds[$session->origin_session_id]))
                <button type="button"
                    @click="showModal = 'part2'"
                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs bg-purple-100 text-purple-700 hover:bg-purple-200 transition-colors mb-1 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                    + Tambah Bagian 2
                </button>
            @endif
            {{-- Badge setelah status diinput --}}
            <div x-show="status !== 'SCHEDULED'" style="display: {{ $session->status !== 'SCHEDULED' ? 'flex' : 'none' }};" class="flex items-center justify-end gap-2">
                {{-- Status selain DIGANTI --}}
                <span x-show="status !== 'DIGANTI'" style="display: {{ $session->status !== 'DIGANTI' ? 'inline-flex' : 'none' }};" class="rounded-lg px-3 py-1.5 text-xs font-semibold border whitespace-normal break-words max-w-[160px] md:max-w-none text-right inline-flex items-center gap-1"
                    :class="{
                        'bg-green-50 text-green-700 border-green-200':    status === 'HADIR',
                        'bg-orange-50 text-orange-700 border-orange-200': status === 'HADIR_TERLAMBAT',
                        'bg-red-50 text-red-700 border-red-200':          status === 'HANGUS',
                        'bg-yellow-50 text-yellow-850 border-yellow-200': status === 'IZIN_RESCHEDULE',
                        'bg-amber-50 text-amber-850 border-amber-200':    status === 'IZIN_PENDING',
                        'bg-blue-50 text-blue-700 border-blue-200':       status === 'IZIN_VIDEO',
                        'bg-mk-surface text-mk-dim border-mk-border':       status === 'CANCELLED',
                    }">
                    <span x-show="loading" class="animate-spin inline-block w-3 h-3 border-2 border-current border-t-transparent rounded-full"></span>
                    <span x-text="
                        status === 'HADIR'            ? '✓ HADIR' :
                        status === 'HADIR_TERLAMBAT'  ? '⏱ +' + lateMinutes + ' mnt' :
                        status === 'HANGUS'            ? '✕ HANGUS' :
                        status === 'IZIN_RESCHEDULE'   ? '📅 ' + (replacementLabel || 'IZIN') :
                        status === 'IZIN_PENDING'      ? '⏳ PENDING' :
                        status === 'IZIN_VIDEO'        ? '📹 VIDEO' :
                        status === 'CANCELLED'         ? 'CANCELLED' : status
                    "></span>
                </span>

                {{-- Status DIGANTI --}}
                <div x-show="status === 'DIGANTI'" style="display: {{ $session->status === 'DIGANTI' ? 'flex' : 'none' }};" class="flex items-center gap-1.5">
                    {{-- Nama Guru Pengganti --}}
                    <span class="rounded-lg px-2.5 py-1.5 text-xs border bg-purple-50 text-purple-700 border-purple-200 font-semibold"
                          x-text="'↔ ' + substituteLabel">
                    </span>
                    {{-- Status Kehadiran --}}
                    <span class="rounded-lg px-2.5 py-1.5 text-xs border font-bold inline-flex items-center gap-1"
                          :class="honorConfirmed ? 'bg-green-50 text-green-700 border-green-200' : 'bg-amber-50 text-amber-700 border-amber-200'">
                        <span x-show="loading" class="animate-spin inline-block w-2.5 h-2.5 border border-current border-t-transparent rounded-full"></span>
                        <span x-text="honorConfirmed ? '✓ HADIR' : '⏳ SCHEDULED'"></span>
                    </span>
                </div>
                {{-- HADIR/HADIR_TERLAMBAT: hanya bisa di-batalkan, tidak bisa diubah ke status lain --}}
                <button x-show="status === 'HADIR' || status === 'HADIR_TERLAMBAT'"
                    style="display: {{ in_array($session->status, ['HADIR', 'HADIR_TERLAMBAT']) ? 'inline-block' : 'none' }};"
                    @click="save('SCHEDULED')"
                    :disabled="loading"
                    class="text-red-500 hover:text-red-700 text-xs font-semibold underline ml-1 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent rounded">batalkan</button>
                {{-- Status lain (termasuk CANCELLED): bisa di-ubah ulang ke SCHEDULED.
                     Disembunyikan jika sesi sudah punya replacement atau sedang menunggu konfirmasi DIGANTI. --}}
                @if(!isset($sessionIdsWithReplacement[$session->id]))
                <button x-show="status !== 'HADIR' && status !== 'HADIR_TERLAMBAT' && !(status === 'DIGANTI' && !honorConfirmed)"
                    style="display: {{ (!in_array($session->status, ['HADIR', 'HADIR_TERLAMBAT']) && !($session->status === 'DIGANTI' && $session->honor_code === null)) ? 'inline-block' : 'none' }};"
                    @click="status = 'SCHEDULED'; errorMsg = ''"
                    class="text-mk-dim hover:text-mk-muted text-xs font-semibold underline ml-1 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent rounded">ubah</button>
                @endif
                {{-- Tombol konfirmasi kehadiran pengganti — hanya tampil jika DIGANTI dan belum dikonfirmasi --}}
                <div x-show="status === 'DIGANTI' && !honorConfirmed"
                     style="display: {{ ($session->status === 'DIGANTI' && $session->honor_code === null) ? 'flex' : 'none' }};"
                     class="flex items-center gap-1.5 ml-1">
                    <button @click="confirmSubstitute('hadir')"
                            :disabled="loading"
                            class="rounded-lg px-2.5 py-1 text-xs font-bold bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100 disabled:opacity-40 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent flex items-center gap-1">
                        <span x-show="loading" class="animate-spin inline-block w-2.5 h-2.5 border border-current border-t-transparent rounded-full"></span>
                        <span x-text="loading ? '...' : '✓ Hadir'"></span>
                    </button>
                    <button @click="confirmSubstitute('batal')"
                            :disabled="loading"
                            class="rounded-lg px-2.5 py-1 text-xs font-bold bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-100 disabled:opacity-40 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent flex items-center gap-1">
                        <span x-show="loading" class="animate-spin inline-block w-2.5 h-2.5 border border-current border-t-transparent rounded-full"></span>
                        <span x-text="loading ? '...' : '✗ Batalkan'"></span>
                    </button>
                </div>
            </div>

            {{-- Tombol aksi (status belum diinput) --}}
            <div x-show="status === 'SCHEDULED'"
                style="display: {{ $session->status === 'SCHEDULED' ? 'flex' : 'none' }};"
                class="flex items-center justify-end gap-1.5"
                :class="loading ? 'opacity-50 pointer-events-none' : ''">

                <button @click="save('HADIR')"
                    class="rounded-lg px-3 py-1.5 text-xs font-bold btn-mk-primary focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                    HADIR
                </button>
                <button @click="save('HANGUS')"
                    class="border border-red-200 text-red-700 bg-red-50/50 hover:bg-red-50 hover:border-red-300 rounded-lg px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wider transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                    HANGUS
                </button>
                {{-- IZIN → buka mini-modal dengan tanggal pengganti. --}}
                <button @click="showModal = 'reschedule'"
                    class="border border-yellow-200 text-yellow-800 bg-yellow-50/50 hover:bg-yellow-50 hover:border-yellow-300 rounded-lg px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wider transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                    IZIN
                </button>
                {{-- IZIN PENDING → izin tanpa tanggal, jadwal menyusul (Open Slot Board). --}}
                <button @click="showModal = 'pending'"
                    class="border border-amber-200 text-amber-800 bg-amber-50/50 hover:bg-amber-50 hover:border-amber-300 rounded-lg px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wider transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                    PENDING
                </button>
                <button @click="save('IZIN_VIDEO')"
                    class="border border-blue-200 text-blue-700 bg-blue-50/50 hover:bg-blue-50 hover:border-blue-300 rounded-lg px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wider transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                    VIDEO
                </button>

                {{-- Tombol DIGANTI --}}
                <button @click="showModal = 'diganti'"
                    class="border border-purple-200 text-purple-700 bg-purple-50/50 hover:bg-purple-50 hover:border-purple-300 rounded-lg px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wider transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                    DIGANTI
                </button>

                <button x-show="dbStatus !== 'SCHEDULED'" type="button" @click="status = dbStatus"
                    class="border border-red-200 text-red-700 bg-red-50 hover:bg-red-100 rounded-lg px-3 py-1.5 text-xs font-bold">
                    BATAL
                </button>
            </div>

            {{-- Modal DIGANTI — di-teleport ke body agar fixed bekerja di mobile --}}
            <template x-teleport="body">
            <div x-show="showModal === 'diganti'"
                x-transition
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                style="display: none;"
                @click.self="showModal = null">
                <div class="bg-mk-card border border-mk-border rounded-xl shadow-xl w-80 p-5 text-left mx-4" @click.stop>
                    <p class="text-mk-muted text-sm font-bold mb-1">Tugaskan Guru Pengganti</p>
                    <p class="text-mk-dim text-xs mb-3 truncate font-medium">
                        {{ $session->student->full_name }} · {{ $session->teacher->name }}
                    </p>
                    <p class="text-xs mb-3 p-2 rounded-lg border text-mk-warning bg-mk-warningBg border-mk-warningBorder">
                        Guru asli berhalangan. Tugaskan pengganti + opsional jam/ruang baru.<br>
                        <span class="font-medium text-mk-muted">Konfirmasi kehadiran setelah sesi berlangsung (⏳).</span>
                    </p>
                    <label class="block text-mk-dim text-xs mb-1 font-semibold">Guru pengganti <span class="text-red-400">*</span></label>
                    <select x-model="substituteId"
                        class="w-full border border-mk-border text-mk-muted rounded-lg px-3 py-1.5 text-sm mb-3 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                        <option value="">— Pilih guru pengganti —</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <div>
                            <label class="block text-mk-dim text-xs mb-1 font-semibold">Jam Mulai <span class="text-mk-dim text-[10px] font-normal">(opsional)</span></label>
                            <input type="time" x-model="substituteStartTime"
                                class="w-full border border-mk-border text-mk-muted rounded-lg px-2 py-1.5 text-sm focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                        </div>
                        <div>
                            <label class="block text-mk-dim text-xs mb-1 font-semibold">Jam Selesai</label>
                            <input type="time" x-model="substituteEndTime"
                                class="w-full border border-mk-border text-mk-muted rounded-lg px-2 py-1.5 text-sm focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-mk-dim text-xs mb-1 font-semibold">Ruangan <span class="text-mk-dim text-[10px] font-normal">(opsional)</span></label>
                        <select x-model="substituteRoomId"
                            class="w-full border border-mk-border text-mk-muted rounded-lg px-2 py-1.5 text-sm focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                            <option value="">— Sama dengan ruangan asli —</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-mk-dim text-[11px] mb-3">Honor ke guru pengganti setelah dikonfirmasi hadir.</p>
                    <div class="flex gap-2">
                        <button @click="if(substituteId) save('DIGANTI', {
                                substitute_teacher_id: substituteId,
                                substitute_start_time: substituteStartTime || null,
                                substitute_end_time: substituteEndTime || null,
                                substitute_room_id: substituteRoomId || null,
                              })"
                            :disabled="!substituteId || loading"
                            class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2 rounded-lg btn-mk-primary focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent flex items-center justify-center gap-1">
                            <span x-show="loading" class="animate-spin inline-block w-3 h-3 border-2 border-current border-t-transparent rounded-full"></span>
                            <span x-text="loading ? 'Menyimpan...' : 'Simpan'"></span>
                        </button>
                        <button @click="showModal = null"
                            :disabled="loading"
                            class="border border-mk-border text-mk-dim hover:bg-mk-surface text-xs py-2 px-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
            </template>

            {{-- Modal RESCHEDULE — di-teleport ke body agar fixed bekerja di mobile --}}
            <template x-teleport="body">
            <div x-show="showModal === 'reschedule'"
                x-transition
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                style="display: none;"
                @click.self="showModal = null; errorMsg = ''">
                <div class="bg-mk-card border border-mk-border rounded-xl shadow-xl w-80 p-5 text-left" @click.stop>
                    <p class="text-mk-muted text-sm font-bold mb-1">Jadwalkan Sesi Pengganti</p>
                    <p class="text-mk-dim text-xs mb-4 truncate font-medium">
                        {{ $session->student->full_name }} · {{ $session->teacher->name }}
                    </p>

                    {{-- Error message dari server (konflik guru/ruangan) --}}
                    <p x-show="errorMsg" x-text="errorMsg"
                        class="bg-red-50 border border-red-200 text-red-600 text-xs rounded-lg px-3 py-2 mb-3">
                    </p>

                    <label class="block text-mk-dim text-xs mb-1 font-semibold">Tanggal Pengganti</label>
                    <input type="date" x-model="rescheduleDate"
                        class="w-full border border-mk-border text-mk-muted rounded-lg px-3 py-1.5 text-sm mb-3 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">

                    <label class="block text-mk-dim text-xs mb-1 font-semibold">Jam Mulai</label>
                    <input type="time" x-model="rescheduleTime"
                        class="w-full border border-mk-border text-mk-muted rounded-lg px-3 py-1.5 text-sm mb-3 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">

                    <label class="block text-mk-dim text-xs mb-1 font-semibold">Ruangan <span class="text-mk-dim font-normal">(opsional)</span></label>
                    <select x-model="rescheduleRoomId"
                        class="w-full border border-mk-border text-mk-muted rounded-lg px-3 py-1.5 text-sm mb-4 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                        <option value="">— Tanpa ruangan —</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>

                    {{-- Toggle: Bagi menjadi 2 bagian — hanya untuk sesi original (bukan hasil split) --}}
                    @if($session->split_part === null)
                    <div class="flex items-center gap-2 mb-4">
                        <button type="button"
                            @click="splitMode = !splitMode"
                            :class="splitMode ? 'bg-amber-600' : 'bg-gray-400'"
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none">
                            <span :class="splitMode ? 'translate-x-5' : 'translate-x-1'"
                                  class="inline-block h-3 w-3 transform rounded-full bg-mk-card transition-transform"></span>
                        </button>
                        <span class="text-xs text-mk-muted font-medium">Bagi menjadi 2 bagian</span>
                        <span x-show="splitMode" class="text-xs text-amber-600 font-bold">(15m + 15m)</span>
                    </div>
                    @endif

                    <div class="flex gap-2">
                        <button type="button"
                            @click="{{ $session->split_part === null ? 'splitMode ? saveSplitPart1() : saveReschedule()' : 'saveReschedule()' }}"
                            :disabled="!rescheduleDate || !rescheduleTime || loading"
                            class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2.5 rounded-lg btn-mk-primary focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent flex items-center justify-center gap-1">
                            <span x-show="loading" class="animate-spin inline-block w-3 h-3 border-2 border-current border-t-transparent rounded-full"></span>
                            <span x-text="loading ? 'Menyimpan...' : (splitMode ? 'Jadwalkan Bagian 1' : 'Buat Sesi Pengganti')"></span>
                        </button>
                        <button @click="showModal = null; errorMsg = ''; splitMode = false"
                            :disabled="loading"
                            class="border border-mk-border text-mk-dim hover:bg-mk-surface text-xs py-2.5 px-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
            </template>

            {{-- Modal IZIN PENDING — di-teleport ke body --}}
            <template x-teleport="body">
            <div x-show="showModal === 'pending'"
                x-transition
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                style="display: none;"
                @click.self="showModal = null; pendingNotes = ''">
                <div class="bg-mk-card border border-mk-border rounded-xl shadow-xl w-80 p-5 text-left" @click.stop>
                    <p class="text-mk-muted text-sm font-bold mb-1">Izin — Tanggal Menyusul</p>
                    <p class="text-mk-dim text-xs mb-3 truncate font-medium">
                        {{ $session->student->full_name }} · {{ $session->teacher->name }}
                    </p>
                    <p class="text-xs mb-3 p-2 rounded-lg border text-mk-warning bg-mk-warningBg border-mk-warningBorder">
                        Sesi masuk Open Slot Board. Jadwal pengganti diatur belakangan.
                    </p>
                    <label class="block text-mk-dim text-xs mb-1 font-semibold">Keterangan <span class="text-mk-dim font-normal">(opsional)</span></label>
                    <textarea x-model="pendingNotes" maxlength="500" rows="3"
                        placeholder="Misal: Murid izin sakit, belum tahu jadwal pengganti"
                        class="w-full border border-mk-border text-mk-muted rounded-lg px-3 py-1.5 text-sm mb-4 resize-none focus:border-mk-accent focus:ring-1 focus:ring-mk-accent"></textarea>
                    <div class="flex gap-2">
                        <button type="button" @click="savePending()"
                            :disabled="loading"
                            class="flex-1 disabled:opacity-40 font-semibold text-xs py-2.5 rounded-lg btn-mk-primary focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent flex items-center justify-center gap-1">
                            <span x-show="loading" class="animate-spin inline-block w-3 h-3 border-2 border-current border-t-transparent rounded-full"></span>
                            <span x-text="loading ? 'Menyimpan...' : 'Simpan Pending'"></span>
                        </button>
                        <button type="button" @click="showModal = null; pendingNotes = ''"
                            :disabled="loading"
                            class="border border-mk-border text-mk-dim hover:bg-mk-surface text-xs py-2.5 px-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
            </template>

            {{-- Modal Bagian 2 — di-teleport ke body --}}
            <template x-teleport="body">
            <div x-show="showModal === 'part2'"
                 x-transition
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                 style="display: none;"
                 @click.self="showModal = null; part2Error = ''">
                <div class="bg-mk-card border border-mk-border rounded-xl shadow-xl w-80 p-5 text-left" @click.stop>
                    <p class="text-mk-muted text-sm font-bold mb-1">Jadwalkan Bagian 2</p>
                    <p class="text-mk-dim text-xs mb-4 truncate font-medium">
                        {{ $session->student->full_name }} · {{ $session->teacher->name }}
                        @php $lbl = $session->getSessionLabel(); @endphp
                        @if($lbl !== '—')
                            &mdash; {{ $lbl }}
                        @endif
                    </p>

                    {{-- Pesan error dari server --}}
                    <p x-show="part2Error" x-text="part2Error"
                        class="bg-red-50 border border-red-200 text-red-600 text-xs rounded-lg px-3 py-2 mb-3">
                    </p>

                    <label class="block text-mk-dim text-xs mb-1 font-semibold">Tanggal</label>
                    <input type="date" x-model="part2Date"
                        class="w-full border border-mk-border text-mk-muted rounded-lg px-3 py-1.5 text-sm mb-3 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">

                    <label class="block text-mk-dim text-xs mb-1 font-semibold">Jam Mulai</label>
                    <input type="time" x-model="part2Time"
                        class="w-full border border-mk-border text-mk-muted rounded-lg px-3 py-1.5 text-sm mb-3 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">

                    <label class="block text-mk-dim text-xs mb-1 font-semibold">Ruangan <span class="text-mk-dim font-normal">(opsional)</span></label>
                    <select x-model="part2RoomId"
                        class="w-full border border-mk-border text-mk-muted rounded-lg px-3 py-1.5 text-sm mb-4 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                        <option value="">— Tanpa ruangan —</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>
                        @endforeach
                    </select>

                    <div class="flex gap-2">
                        <button type="button" @click="saveSplitPart2()"
                            :disabled="!part2Date || !part2Time || loading"
                            class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2.5 rounded-lg bg-purple-650 text-white hover:bg-purple-750 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent flex items-center justify-center gap-1">
                            <span x-show="loading" class="animate-spin inline-block w-3 h-3 border-2 border-current border-t-transparent rounded-full"></span>
                            <span x-text="loading ? 'Menyimpan...' : 'Jadwalkan Bagian 2'"></span>
                        </button>
                        <button type="button" @click="showModal = null; part2Error = ''"
                            :disabled="loading"
                            class="border border-mk-border text-mk-dim hover:bg-mk-surface text-xs py-2.5 px-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
            </template>

        @endif
    </td>
</tr>
