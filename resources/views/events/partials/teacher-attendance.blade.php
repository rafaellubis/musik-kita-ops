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
        // Judul panel pindah ke header slide-over, jadi nama + instrumen guru dikirim sebagai peta.
        names: @js($assignments->mapWithKeys(fn ($assignment) => [(int) $assignment->id => [
            'nama' => $assignment->teacher->name,
            'instrumen' => $ringkasan[$assignment->id]['instrumen'] ?: 'Instrumen belum tercatat',
        ]])),
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

    {{-- Slide-over detail: satu panel per guru, berisi murid yang didampingi + kontrol absensinya.
         Pola animasi sengaja disamakan dengan panel detail di halaman Kalender (x-show + x-transition). --}}
    <div x-show="panelOpen" x-cloak @click.self="close()" @keydown.escape.window="closeFromEscape()"
         class="fixed inset-0 z-50 flex justify-end bg-black/40">
        <div x-show="panelOpen" role="dialog" aria-modal="true" aria-label="Detail guru pendamping"
             x-transition:enter="transform transition ease-in-out duration-300"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transform transition ease-in-out duration-300"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="flex h-full w-full max-w-[30rem] flex-col border-l border-mk-border/30 bg-mk-card text-mk-text shadow-2xl">
            <div class="flex items-start justify-between gap-3 bg-mk-sidebar p-6 text-white">
                <div class="min-w-0">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-mk-accent">Detail guru pendamping</span>
                    <h3 class="mt-1 truncate text-lg font-bold text-white" x-text="namaGuru"></h3>
                    <p class="mt-0.5 text-xs text-white/80" x-text="instrumenGuru"></p>
                </div>
                <button type="button" x-on:click="close()" aria-label="Tutup"
                        class="-mr-1 cursor-pointer p-1 text-2xl font-bold leading-none text-mk-accent transition-colors hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">&times;</button>
            </div>

            <div class="flex-1 overflow-y-auto">
                @foreach($assignments as $assignment)
                    @php $info = $ringkasan[$assignment->id]; @endphp
                    <section x-show="detail === {{ $assignment->id }}" data-teacher-detail="{{ $assignment->id }}" class="space-y-4 p-4">
                        {{-- Nama + instrumen guru sudah tampil di header panel, di sini cukup progresnya. --}}
                        <p class="text-sm text-mk-muted">{{ $info['jumlah'] }} murid didampingi · {{ $info['tercatat'] }} dari {{ $info['jumlah'] }} absensi murid</p>

                        @if($canManageAttendance && $assignment->attendance_status !== EventTeacherAssignment::ATTENDANCE_HADIR)
                            <p class="rounded-lg border border-mk-warningBorder bg-mk-warningBg p-3 text-sm font-semibold text-mk-warning">Guru pendamping harus Hadir sebelum absensi murid diisi.</p>
                        @endif

                        <div class="divide-y divide-mk-borderLight rounded-lg border border-mk-borderLight">
                            @foreach($info['peserta'] as $participant)
                                @php
                                    $participantMeta = $participantStatuses[$participant->attendance_status] ?? [$participant->attendance_status, 'bg-mk-surface text-mk-text border-mk-border', 'bg-mk-muted'];
                                    // Tombol Hadir/Tidak hadir + field catatan hanya tampil selama absensi murid
                                    // masih kosong (dan guru pendamping sudah Hadir). Begitu statusnya tersimpan,
                                    // baris murid cukup menampilkan status + tombol Reset absensi.
                                    $canOperateParticipant = $canManageAttendance
                                        && $assignment->attendance_status === EventTeacherAssignment::ATTENDANCE_HADIR
                                        && $participant->attendance_status === EventParticipant::ATTENDANCE_PENDING;
                                    // Ganti guru & reset absensi tetap memakai gate mutasi lama (tidak bergantung status guru).
                                    $canManageParticipant = $canManageAttendance;
                                    // Satu definisi tombol sekunder untuk panel ini: target sentuh minimal 44px
                                    // (min-h-11) supaya nyaman dipakai lewat layar sentuh studio.
                                    $tombolSekunder = 'min-h-11 cursor-pointer rounded-lg border border-mk-border px-3 py-2 text-sm font-semibold text-mk-muted transition-colors hover:bg-mk-surface hover:text-mk-text';
                                @endphp
                                {{-- Grid 2 kolom: baris 1 = nama + badge status, baris 2 = tombol absensi +
                                     tombol Catatan. Field catatan mengisi satu baris penuh di bawahnya. --}}
                                <article class="grid grid-cols-1 gap-3 px-4 py-4 sm:grid-cols-[1fr_auto] sm:items-start"
                                         x-data="{ catatan: false }">
                                    {{-- Kalau baris 2 kolom kiri kosong (absensi sudah tersimpan / tidak bisa
                                         diubah), identitas membentang dua baris. Tanpa itu tingginya blok nama
                                         mendorong badge dan tombol Reset berjauhan (jarak jadi 56px, bukan 12px). --}}
                                    <div class="min-w-0 {{ $canOperateParticipant ? '' : 'sm:row-span-2' }}">
                                        <h4 class="font-semibold text-mk-text">{{ $participant->student->full_name }}</h4>
                                        {{-- Baris kedua: "Nickname · Instrumen". array_filter supaya pemisah "·" tidak muncul kalau nickname kosong. --}}
                                        <p class="mt-0.5 text-sm text-mk-muted">{{ implode(' · ', array_filter([$participant->student->nickname, $participant->snapshot_instrument_name])) }}</p>
                                        @if($participant->event_notes)
                                            <p class="mt-2 text-sm text-mk-text"><span class="font-semibold">Catatan:</span> {{ $participant->event_notes }}</p>
                                        @endif
                                    </div>

                                    {{-- Kolom kanan baris 1: status absensi murid. --}}
                                    <span class="inline-flex items-center gap-1.5 justify-self-start rounded-full border px-2.5 py-1 text-xs font-semibold sm:justify-self-end {{ $participantMeta[1] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $participantMeta[2] }}" aria-hidden="true"></span>
                                        {{ $participantMeta[0] }}
                                    </span>

                                    @if($recoveringParticipantId === $participant->id)
                                        <div class="space-y-1 sm:col-span-2">
                                            @foreach($errors->all() as $pesan)
                                                <p class="rounded-lg border border-error/30 bg-error-container p-2 text-sm font-medium text-on-error-container">{{ $pesan }}</p>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($canOperateParticipant)
                                        {{-- Kolom kiri baris 2: sekali klik status terkirim. Field catatan ada di
                                             dropdown baris bawah, tapi tetap milik form ini (lihat atribut form=)
                                             supaya status + catatan terkirim bersamaan. --}}
                                        <form id="absen-murid-{{ $participant->id }}" method="POST"
                                              action="{{ route('event-participants.attendance', $participant) }}" class="flex flex-wrap gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="event_ui_tab" value="teacher-attendance">
                                            <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                            <button type="submit" name="attendance_status" value="HADIR"
                                                    aria-label="Tandai {{ $participant->student->full_name }} hadir"
                                                    class="{{ $tombolSekunder }}">Hadir</button>
                                            <button type="submit" name="attendance_status" value="TIDAK_HADIR"
                                                    aria-label="Tandai {{ $participant->student->full_name }} tidak hadir"
                                                    class="{{ $tombolSekunder }}">Tidak hadir</button>
                                        </form>

                                        {{-- Kolom kanan baris 2: dropdown catatan, sejajar dengan tombol absensi. --}}
                                        <button type="button" x-on:click="catatan = ! catatan" :aria-expanded="catatan.toString()"
                                                aria-controls="catatan-{{ $participant->id }}"
                                                class="inline-flex cursor-pointer items-center gap-1.5 justify-self-start {{ $tombolSekunder }} focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent sm:justify-self-end">
                                            {{ $participant->event_notes ? 'Ubah catatan' : 'Catatan (opsional)' }}
                                            {{-- Panah penanda dropdown, diputar saat panelnya terbuka. --}}
                                            <svg class="h-3.5 w-3.5 transition-transform" :class="catatan ? 'rotate-180' : ''"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>

                                        {{-- Field catatan: satu baris penuh di bawah, jadi lebarnya selebar panel. --}}
                                        <div id="catatan-{{ $participant->id }}" x-show="catatan" style="display:none" class="sm:col-span-2">
                                            <label for="participant-notes-{{ $participant->id }}" class="block text-sm font-semibold text-mk-text">Catatan</label>
                                            <input id="participant-notes-{{ $participant->id }}" type="text" name="event_notes" maxlength="1000"
                                                   value="{{ $participant->event_notes }}"
                                                   form="absen-murid-{{ $participant->id }}"
                                                   placeholder="Misal: bawa partitur, perlu latihan tambahan"
                                                   class="mt-1 w-full rounded-md text-sm">
                                        </div>
                                    @endif

                                    @if($canManageParticipant && $participant->attendance_status !== EventParticipant::ATTENDANCE_PENDING)
                                        <div class="justify-self-start text-right sm:col-start-2 sm:justify-self-end" x-data="{ bukaReset: false }">
                                            <button type="button" x-on:click="bukaReset = ! bukaReset"
                                                    :aria-expanded="bukaReset.toString()" aria-controls="reset-panel-{{ $participant->id }}"
                                                    class="{{ $tombolSekunder }} focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">Reset absensi</button>
                                            <form id="reset-panel-{{ $participant->id }}" x-show="bukaReset" style="display:none"
                                                  method="POST" action="{{ route('event-participants.attendance.reset', $participant) }}"
                                                  class="mt-2 flex flex-wrap items-end justify-end gap-2">
                                                @csrf
                                                <input type="hidden" name="event_ui_tab" value="teacher-attendance">
                                                <div class="min-w-[14rem] flex-1 text-left">
                                                    <label for="reset-reason-{{ $participant->id }}" class="block text-sm font-semibold text-mk-text">Alasan reset absensi</label>
                                                    <input id="reset-reason-{{ $participant->id }}" type="text" name="reason" required maxlength="1000" class="mt-1 w-full rounded-md text-sm">
                                                </div>
                                                <button type="submit" class="{{ $tombolSekunder }} focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">Reset Absensi</button>
                                            </form>
                                            <p class="mt-2 text-sm text-mk-muted">Reset absensi sebelum mengganti guru.</p>
                                        </div>
                                    @endif

                                    {{-- Ganti guru pendamping hanya relevan kalau guru yang mendampingi sekarang
                                         TIDAK hadir. Absensi murid juga harus masih kosong (aturan server). --}}
                                    @if($canManageParticipant
                                        && $participant->attendance_status === EventParticipant::ATTENDANCE_PENDING
                                        && $assignment->attendance_status === EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR)
                                        <button type="button" onclick="document.getElementById('reassign-{{ $participant->id }}').showModal()"
                                                class="justify-self-start sm:col-start-2 sm:justify-self-end {{ $tombolSekunder }} focus:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">
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
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</div>
