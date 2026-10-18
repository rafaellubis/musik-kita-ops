@php
    use App\Models\EventParticipant;
    use App\Models\EventTeacherAssignment;

    $activeParticipants = $event->participants->where('status', 'ACTIVE');
    $assignments = $activeParticipants->pluck('activeTeacherAssignment')->filter()->unique('id')->values();
    $attendanceOpen = $event->event_date->lte(today());
    $teacherStatuses = [
        EventTeacherAssignment::ATTENDANCE_PENDING => ['Belum dikonfirmasi', 'bg-amber-50 text-amber-900 border-amber-200', 'bg-amber-500'],
        EventTeacherAssignment::ATTENDANCE_HADIR => ['Hadir', 'bg-emerald-50 text-emerald-900 border-emerald-200', 'bg-emerald-600'],
        EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR => ['Tidak hadir', 'bg-red-50 text-red-900 border-red-200', 'bg-red-600'],
    ];

    // Label murid tidak boleh memakai $teacherStatuses: istilah "dikonfirmasi" hanya untuk guru,
    // sedangkan absensi murid dicatat. Karena itu dipakai peta label sendiri.
    $participantStatuses = [
        EventParticipant::ATTENDANCE_PENDING => ['Belum dicatat', 'bg-amber-50 text-amber-900 border-amber-200', 'bg-amber-500'],
        EventParticipant::ATTENDANCE_HADIR => ['Hadir', 'bg-emerald-50 text-emerald-900 border-emerald-200', 'bg-emerald-600'],
        EventParticipant::ATTENDANCE_TIDAK_HADIR => ['Tidak hadir', 'bg-red-50 text-red-900 border-red-200', 'bg-red-600'],
    ];

    // Penjelasan kenapa form absensi tidak muncul, supaya keadaan terkunci tidak membingungkan.
    $tabNotice = match (true) {
        $event->isDraft() => $canMutate
            ? 'Event masih Draft. Publikasikan event untuk mulai mencatat kehadiran guru dan murid.'
            : 'Event masih Draft. Kehadiran belum dapat dicatat.',
        $event->isPublished() && ! $attendanceOpen => 'Absensi dibuka mulai tanggal event.',
        $event->isCompleted() => 'Event telah selesai. Kehadiran hanya dapat dilihat.',
        $event->isCancelled() => 'Event telah dibatalkan. Kehadiran hanya dapat dilihat.',
        default => null,
    };

    // Ringkasan per guru dipakai dua kali (kartu + panel drawer) supaya tidak dihitung ulang.
    $ringkasan = $assignments->mapWithKeys(function ($assignment) use ($activeParticipants) {
        $peserta = $activeParticipants->where('active_teacher_assignment_id', $assignment->id);

        return [$assignment->id => [
            'peserta' => $peserta,
            'jumlah' => $peserta->count(),
            'tercatat' => $peserta->whereNotIn('attendance_status', [EventParticipant::ATTENDANCE_PENDING])->count(),
            'instrumen' => $peserta->pluck('snapshot_instrument_name')->filter()->unique()->join(', '),
        ]];
    });

    // Gate mutasi lama, tanpa syarat per-peserta.
    $canManageAttendance = $canMutate && $event->isPublished() && $attendanceOpen;
    // Pemulihan setelah validasi gagal: buka lagi panel drawer yang sedang dikerjakan.
    $recoveringAssignmentId = (int) old('assignment_id') ?: null;
    $recoveringParticipantId = (int) old('participant_id') ?: null;
    $drawerAwal = $recoveringAssignmentId
        ?? ($recoveringParticipantId
            ? $activeParticipants->firstWhere('id', $recoveringParticipantId)?->active_teacher_assignment_id
            : null);
@endphp

<div class="space-y-5"
     x-data="eventTeacherDetail({
        eventId: {{ $event->id }},
        initial: @js($drawerAwal),
        ids: @js($assignments->pluck('id')->map(fn ($id) => (int) $id)->values()->all()),
     })">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-mk-text">Kehadiran guru</h2>
            <p class="mt-1 text-sm text-mk-muted">Konfirmasi kehadiran setiap guru pendamping. Daftar murid yang didampingi ada di tombol Detail.</p>
        </div>
        <div class="text-sm font-semibold text-mk-text">{{ $assignments->count() }} guru pendamping</div>
    </div>

    @if($tabNotice)
        <div class="rounded-lg border border-mk-warningBorder bg-mk-warningBg p-4 text-sm font-semibold text-mk-warning">{{ $tabNotice }}</div>
    @endif

    @if($assignments->isEmpty())
        <div class="rounded-lg border border-dashed border-mk-border bg-mk-card px-5 py-10 text-center">
            <p class="font-semibold text-mk-text">Belum ada guru pendamping.</p>
            <p class="mt-1 text-sm text-mk-muted">Guru akan muncul setelah peserta memiliki assignment aktif.</p>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
            @foreach($assignments as $assignment)
                @php
                    $info = $ringkasan[$assignment->id];
                    $status = $teacherStatuses[$assignment->attendance_status] ?? [$assignment->attendance_status, 'bg-mk-surface text-mk-text border-mk-border', 'bg-mk-muted'];
                    $recoveringAssignment = $recoveringAssignmentId === $assignment->id;
                    $reason = $recoveringAssignment ? old('reason') : $assignment->absence_reason;
                    // Guru yang statusnya sudah ditentukan (Hadir/Tidak Hadir) hanya menyisakan tombol "Batalkan".
                    $sudahDikonfirmasi = in_array($assignment->attendance_status, [
                        EventTeacherAssignment::ATTENDANCE_HADIR,
                        EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR,
                    ], true);
                    // Panel alasan hanya dibuka ulang saat submit "Tidak hadir" gagal validasi.
                    // Status yang sudah tersimpan tampil sebagai teks, BUKAN sebagai field input.
                    $absenState = $recoveringAssignment && old('attendance_status') === EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR;
                    // Absensi murid yang sudah tercatat harus di-reset dulu sebelum kehadiran guru dibatalkan.
                    $bolehBatalkan = $info['tercatat'] === 0;
                @endphp
                <article class="flex flex-col gap-3 rounded-lg border border-mk-borderLight bg-mk-card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-mk-text">{{ $assignment->teacher->name }}</h3>
                            <p class="mt-1 text-sm text-mk-muted">{{ $info['instrumen'] ?: 'Instrumen belum tercatat' }} · {{ $info['jumlah'] }} murid</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $status[1] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $status[2] }}" aria-hidden="true"></span>
                            {{ $status[0] }}
                        </span>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-xs font-semibold text-mk-muted">
                            <span>Progres absensi murid</span>
                            <span class="tabular-nums">{{ $info['tercatat'] }} dari {{ $info['jumlah'] }} absensi murid</span>
                        </div>
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-mk-surface" aria-hidden="true">
                            <div class="h-full rounded-full bg-mk-accent" style="width: {{ $info['jumlah'] ? round($info['tercatat'] / $info['jumlah'] * 100) : 0 }}%"></div>
                        </div>
                    </div>

                    @if($assignment->attendance_status === EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR && $assignment->absence_reason)
                        {{-- Alasan tampil sebagai teks di bawah status kehadiran, bukan sebagai field input. --}}
                        <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                            <span class="font-semibold">Alasan tidak hadir:</span> {{ $assignment->absence_reason }}
                        </p>
                    @endif
                    @if($assignment->confirmed_at)
                        <p class="text-xs text-mk-muted">Dikonfirmasi oleh {{ $assignment->confirmedBy?->name ?? 'Pengguna tidak tersedia' }} · {{ $assignment->confirmed_at->translatedFormat('d M Y, H:i') }}</p>
                    @endif

                    <div class="mt-auto space-y-2">
                        @if($canManageAttendance)
                            {{-- Satu form per guru; nilai status dibawa tombol submit.
                                 - Belum dikonfirmasi : pilih Hadir / Tidak hadir ("Tidak hadir" membuka field alasan).
                                 - Hadir / Tidak hadir: hanya tombol Batalkan yang mengembalikan ke Belum dikonfirmasi. --}}
                            <form method="POST" action="{{ route('event-teacher-assignments.attendance', $assignment) }}"
                                  class="flex flex-col gap-2" x-data="{ absen: {{ $absenState ? 'true' : 'false' }} }">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="event_ui_tab" value="teacher-attendance">
                                <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">

                                @if($sudahDikonfirmasi)
                                    <button type="submit" name="attendance_status" value="PENDING" @disabled(! $bolehBatalkan)
                                            class="min-h-11 cursor-pointer rounded-lg border border-mk-border px-4 py-2 text-sm font-semibold text-mk-text hover:bg-mk-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-transparent">Batalkan</button>
                                    @unless($bolehBatalkan)
                                        <p class="text-xs text-mk-muted">Reset absensi murid terlebih dahulu sebelum membatalkan kehadiran guru.</p>
                                    @endunless
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        {{-- jalur "Hadir" tidak boleh diblokir field alasan yang sedang terbuka. --}}
                                        <button type="submit" name="attendance_status" value="HADIR" formnovalidate
                                                class="btn-mk-primary min-h-11 flex-1 cursor-pointer rounded-lg px-3 py-2 text-sm font-bold focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">Hadir</button>
                                        <button type="button" x-on:click="absen = ! absen" :aria-expanded="absen.toString()" aria-controls="absen-{{ $assignment->id }}"
                                                class="min-h-11 flex-1 cursor-pointer rounded-lg border border-mk-border px-3 py-2 text-sm font-semibold text-mk-text hover:bg-mk-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">Tidak hadir</button>
                                    </div>

                                    {{-- Alasan wajib dari server: field hanya dibuka di jalur "Tidak hadir". --}}
                                    <div id="absen-{{ $assignment->id }}" x-show="absen" @if(! $absenState) style="display:none" @endif
                                         class="rounded-lg border border-red-200 bg-red-50 p-3">
                                        <label for="teacher-reason-{{ $assignment->id }}" class="block text-sm font-semibold text-red-900">Alasan tidak hadir</label>
                                        <input id="teacher-reason-{{ $assignment->id }}" type="text" name="reason" maxlength="1000" value="{{ $reason }}" x-bind:required="absen"
                                               @if($recoveringAssignment && $errors->has('reason')) aria-invalid="true" aria-describedby="event-validation-errors" @endif
                                               class="mt-1 w-full rounded-md text-sm">
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <button type="submit" name="attendance_status" value="TIDAK_HADIR"
                                                    class="flex-1 cursor-pointer rounded-lg bg-red-800 px-4 py-2 text-sm font-bold text-white hover:bg-red-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">Simpan Tidak Hadir</button>
                                            <button type="button" x-on:click="absen = false"
                                                    class="cursor-pointer rounded-lg border border-mk-border px-4 py-2 text-sm font-semibold text-mk-text hover:bg-mk-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">Batal</button>
                                        </div>
                                    </div>
                                @endif
                            </form>
                        @endif

                        <button type="button" x-on:click="open({{ $assignment->id }})" aria-haspopup="dialog"
                                class="min-h-11 w-full cursor-pointer rounded-lg border border-mk-border px-3 py-2 text-sm font-semibold text-mk-text hover:bg-mk-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">
                            Detail ({{ $info['jumlah'] }})
                        </button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    {{-- Slide-over detail: satu panel per guru, berisi murid yang didampingi + kontrol absensinya. --}}
    <dialog x-ref="drawer" @close="onClose()" @click="closeOnBackdrop($event)"
            aria-label="Detail guru pendamping"
            class="m-0 ml-auto h-full max-h-none w-full max-w-[30rem] border-0 bg-mk-card p-0 text-mk-text shadow-lg backdrop:bg-black/40">
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between gap-3 border-b border-mk-borderLight px-4 py-3">
                <p class="text-xs font-semibold text-mk-muted">Detail guru pendamping</p>
                <button type="button" x-on:click="close()"
                        class="cursor-pointer rounded-lg border border-mk-border px-3 py-1.5 text-sm font-semibold text-mk-muted hover:bg-mk-surface hover:text-mk-text focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">Tutup</button>
            </div>

            <div class="flex-1 overflow-y-auto">
                @foreach($assignments as $assignment)
                    @php $info = $ringkasan[$assignment->id]; @endphp
                    <section x-show="detail === {{ $assignment->id }}" data-teacher-detail="{{ $assignment->id }}" class="space-y-4 p-4">
                        <div>
                            <h2 class="text-lg font-semibold text-mk-text">Murid didampingi {{ $assignment->teacher->name }}</h2>
                            <p class="mt-1 text-sm text-mk-muted">{{ $info['instrumen'] ?: 'Instrumen belum tercatat' }} · {{ $info['jumlah'] }} murid · {{ $info['tercatat'] }} dari {{ $info['jumlah'] }} absensi murid</p>
                        </div>

                        @if($canManageAttendance && $assignment->attendance_status !== EventTeacherAssignment::ATTENDANCE_HADIR)
                            <p class="rounded-lg border border-mk-warningBorder bg-mk-warningBg p-3 text-sm font-semibold text-mk-warning">Guru pendamping harus Hadir sebelum absensi murid diisi.</p>
                        @endif

                        <div class="divide-y divide-mk-borderLight rounded-lg border border-mk-borderLight">
                            @foreach($info['peserta'] as $participant)
                                @php
                                    $participantMeta = $participantStatuses[$participant->attendance_status] ?? [$participant->attendance_status, 'bg-mk-surface text-mk-text border-mk-border', 'bg-mk-muted'];
                                    // Absensi murid hanya bisa dicatat setelah guru pendamping dikonfirmasi hadir.
                                    $canOperateParticipant = $canManageAttendance
                                        && $assignment->attendance_status === EventTeacherAssignment::ATTENDANCE_HADIR;
                                    // Ganti guru & reset absensi tetap memakai gate mutasi lama (tidak bergantung status guru).
                                    $canManageParticipant = $canManageAttendance;
                                    $tombolAktif = 'rounded-lg px-3 py-2 text-sm font-bold';
                                    $tombolNonaktif = 'rounded-lg border border-mk-border px-3 py-2 text-sm font-semibold text-mk-muted transition-colors hover:bg-mk-surface hover:text-mk-text';
                                @endphp
                                <article class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <h4 class="font-semibold text-mk-text">{{ $participant->student->full_name }}</h4>
                                        <p class="mt-0.5 text-sm text-mk-muted">
                                            {{ $participant->snapshot_instrument_name }} · {{ $participant->snapshot_package_code }}@if($participant->snapshot_grade) · {{ $participant->snapshot_grade }}@endif
                                        </p>
                                        @if($participant->event_notes)
                                            <p class="mt-2 text-sm text-mk-text"><span class="font-semibold">Catatan:</span> {{ $participant->event_notes }}</p>
                                        @endif
                                    </div>

                                    <div class="flex flex-col items-start gap-2 sm:items-end">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $participantMeta[1] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $participantMeta[2] }}" aria-hidden="true"></span>
                                            {{ $participantMeta[0] }}
                                        </span>

                                        @if($recoveringParticipantId === $participant->id)
                                            @foreach($errors->all() as $pesan)
                                                <p class="rounded-lg border border-error/30 bg-error-container p-2 text-sm font-medium text-on-error-container">{{ $pesan }}</p>
                                            @endforeach
                                        @endif

                                        @if($canOperateParticipant)
                                            {{-- Sekali klik: status terkirim lewat tombol submit. Catatan ikut dikirim
                                                 supaya klik ulang tidak menghapus catatan yang sudah ada. --}}
                                            <form method="POST" action="{{ route('event-participants.attendance', $participant) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="event_ui_tab" value="teacher-attendance">
                                                <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                                <input id="participant-notes-{{ $participant->id }}" type="text" name="event_notes" maxlength="1000"
                                                       value="{{ $participant->event_notes }}" placeholder="Catatan (opsional)"
                                                       aria-label="Catatan {{ $participant->student->full_name }}"
                                                       class="w-48 rounded-md text-sm">
                                                <button type="submit" name="attendance_status" value="HADIR"
                                                        aria-label="Tandai {{ $participant->student->full_name }} hadir"
                                                        class="{{ $participant->attendance_status === EventParticipant::ATTENDANCE_HADIR ? $tombolAktif.' btn-mk-primary' : $tombolNonaktif }}">Hadir</button>
                                                <button type="submit" name="attendance_status" value="TIDAK_HADIR"
                                                        aria-label="Tandai {{ $participant->student->full_name }} tidak hadir"
                                                        class="{{ $participant->attendance_status === EventParticipant::ATTENDANCE_TIDAK_HADIR ? $tombolAktif.' bg-red-800 text-white' : $tombolNonaktif }}">Tidak hadir</button>
                                            </form>
                                        @endif

                                        @if($canManageParticipant && $participant->attendance_status !== EventParticipant::ATTENDANCE_PENDING)
                                            <div class="text-right" x-data="{ bukaReset: false }">
                                                <button type="button" x-on:click="bukaReset = ! bukaReset"
                                                        :aria-expanded="bukaReset.toString()" aria-controls="reset-panel-{{ $participant->id }}"
                                                        class="text-sm font-semibold text-mk-muted underline-offset-4 hover:text-mk-text hover:underline">Reset absensi</button>
                                                <form id="reset-panel-{{ $participant->id }}" x-show="bukaReset" style="display:none"
                                                      method="POST" action="{{ route('event-participants.attendance.reset', $participant) }}"
                                                      class="mt-2 flex flex-wrap items-end justify-end gap-2">
                                                    @csrf
                                                    <input type="hidden" name="event_ui_tab" value="teacher-attendance">
                                                    <div class="min-w-[14rem] flex-1 text-left">
                                                        <label for="reset-reason-{{ $participant->id }}" class="block text-sm font-semibold text-mk-text">Alasan reset absensi</label>
                                                        <input id="reset-reason-{{ $participant->id }}" type="text" name="reason" required maxlength="1000" class="mt-1 w-full rounded-md text-sm">
                                                    </div>
                                                    <button type="submit" class="rounded-lg border border-mk-border px-4 py-2 text-sm font-semibold text-mk-muted hover:bg-mk-surface hover:text-mk-text">Reset Absensi</button>
                                                </form>
                                                <p class="mt-2 text-sm text-mk-muted">Reset absensi sebelum mengganti guru.</p>
                                            </div>
                                        @endif

                                        @if($canManageParticipant && $participant->attendance_status === EventParticipant::ATTENDANCE_PENDING)
                                            <button type="button" onclick="document.getElementById('reassign-{{ $participant->id }}').showModal()"
                                                    class="mt-3 text-sm font-semibold text-mk-muted underline-offset-4 hover:text-mk-text hover:underline">
                                                Ganti guru pendamping
                                            </button>
                                            <dialog id="reassign-{{ $participant->id }}" aria-labelledby="reassign-title-{{ $participant->id }}"
                                                    class="w-[min(32rem,calc(100%-2rem))] rounded-lg border-0 bg-white p-0 text-mk-text shadow-lg backdrop:bg-black/40">
                                                <form method="POST" action="{{ route('event-participants.reassign', $participant) }}" class="p-5">
                                                    @csrf
                                                    @method('PATCH')
                                                    <h5 id="reassign-title-{{ $participant->id }}" class="text-lg font-semibold">Ganti guru {{ $participant->student->full_name }}</h5>
                                                    <p class="mt-1 text-sm text-mk-muted">Perubahan hanya berlaku untuk enrollment {{ $participant->snapshot_package_code }}.</p>
                                                    <div class="mt-4">
                                                        <label for="replacement-teacher-{{ $participant->id }}" class="block text-sm font-semibold text-mk-text">Guru pengganti</label>
                                                        <select id="replacement-teacher-{{ $participant->id }}" name="teacher_id" required class="mt-1 w-full rounded-md">
                                                            <option value="">Pilih guru aktif</option>
                                                            @foreach($activeTeachers->where('id', '!=', $assignment->teacher_id) as $teacher)
                                                                <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="mt-4">
                                                        <label for="replacement-reason-{{ $participant->id }}" class="block text-sm font-semibold text-mk-text">Alasan penggantian</label>
                                                        <textarea id="replacement-reason-{{ $participant->id }}" name="reason" required maxlength="1000" rows="3" class="mt-1 w-full rounded-md"></textarea>
                                                    </div>
                                                    <div class="mt-5 flex justify-end gap-2">
                                                        <button type="button" onclick="this.closest('dialog').close()" class="rounded-lg px-4 py-2 text-sm font-semibold text-mk-muted hover:bg-mk-surface">Batal</button>
                                                        <button type="submit" class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold">Simpan Pengganti</button>
                                                    </div>
                                                </form>
                                            </dialog>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </dialog>
</div>
