@php
    $activeParticipants = $event->participants->where('status', 'ACTIVE');
    $assignments = $activeParticipants->pluck('activeTeacherAssignment')->filter()->unique('id')->values();
    $attendanceOpen = $event->event_date->lte(today());
    $teacherStatuses = [
        'PENDING' => ['Belum dikonfirmasi', 'bg-amber-50 text-amber-900 border-amber-200', 'bg-amber-500'],
        'HADIR' => ['Hadir', 'bg-emerald-50 text-emerald-900 border-emerald-200', 'bg-emerald-600'],
        'TIDAK_HADIR' => ['Tidak hadir', 'bg-red-50 text-red-900 border-red-200', 'bg-red-600'],
    ];
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-mk-text">Kehadiran guru dan murid</h2>
            <p class="mt-1 text-sm text-mk-muted">Konfirmasi guru lebih dahulu, lalu catat kehadiran setiap enrollment.</p>
        </div>
        <div class="text-sm font-semibold text-mk-text">{{ $assignments->count() }} guru pendamping</div>
    </div>

    @if(!$attendanceOpen && $event->isPublished())
        <div class="rounded-lg border border-mk-warningBorder bg-mk-warningBg p-4 text-sm font-semibold text-mk-warning">
            Absensi dibuka mulai tanggal event.
        </div>
    @endif

    @forelse($assignments as $assignment)
        @php
            $participants = $activeParticipants->where('active_teacher_assignment_id', $assignment->id);
            $recordedCount = $participants->whereNotIn('attendance_status', ['PENDING'])->count();
            $status = $teacherStatuses[$assignment->attendance_status] ?? [$assignment->attendance_status, 'bg-mk-surface text-mk-text border-mk-border', 'bg-mk-muted'];
            $instruments = $participants->pluck('snapshot_instrument_name')->filter()->unique()->join(', ');
            $recoveringAssignment = (string) old('assignment_id') === (string) $assignment->id;
            $selectedAttendance = $recoveringAssignment ? old('attendance_status') : $assignment->attendance_status;
            $reason = $recoveringAssignment ? old('reason') : $assignment->absence_reason;
        @endphp

        <section class="overflow-hidden rounded-lg border border-mk-borderLight bg-mk-card">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-mk-borderLight bg-mk-surface px-4 py-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-mk-text">{{ $assignment->teacher->name }}</h3>
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $status[1] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $status[2] }}" aria-hidden="true"></span>
                            {{ $status[0] }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-mk-muted">{{ $instruments ?: 'Instrumen belum tercatat' }} · {{ $participants->count() }} peserta</p>
                    @if($assignment->confirmed_at)
                        <p class="mt-1 text-xs text-mk-muted">
                            Dikonfirmasi oleh {{ $assignment->confirmedBy?->name ?? 'Pengguna tidak tersedia' }} · {{ $assignment->confirmed_at->translatedFormat('d M Y, H:i') }}
                        </p>
                    @endif
                    @if($assignment->attendance_status === 'TIDAK_HADIR' && $assignment->absence_reason)
                        <p class="mt-2 text-sm text-red-900"><span class="font-semibold">Alasan:</span> {{ $assignment->absence_reason }}</p>
                    @endif
                </div>
                <div class="rounded-lg border border-mk-borderLight bg-white px-3 py-2 text-right">
                    <div class="text-xs font-semibold text-mk-muted">Progres peserta</div>
                    <div class="mt-0.5 text-sm font-bold text-mk-text">{{ $recordedCount }} dari {{ $participants->count() }} absensi murid</div>
                </div>
            </div>

            @if($canMutate && $event->isPublished() && $attendanceOpen)
                <form method="POST" action="{{ route('event-teacher-assignments.attendance', $assignment) }}"
                      class="grid gap-3 border-b border-mk-borderLight px-4 py-4 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_auto] sm:items-end">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="event_ui_tab" value="teacher-attendance">
                    <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">
                    <div>
                        <label for="teacher-attendance-{{ $assignment->id }}" class="block text-sm font-semibold text-mk-text">Kehadiran guru</label>
                        <select id="teacher-attendance-{{ $assignment->id }}" name="attendance_status" required class="mt-1 w-full rounded-md text-sm">
                            <option value="HADIR" @selected($selectedAttendance === 'HADIR')>Hadir</option>
                            <option value="TIDAK_HADIR" @selected($selectedAttendance === 'TIDAK_HADIR')>Tidak hadir</option>
                        </select>
                    </div>
                    <div>
                        <label for="teacher-reason-{{ $assignment->id }}" class="block text-sm font-semibold text-mk-text">Alasan jika tidak hadir</label>
                        <input id="teacher-reason-{{ $assignment->id }}" type="text" name="reason" maxlength="1000"
                               value="{{ $reason }}" class="mt-1 w-full rounded-md text-sm"
                               @if($recoveringAssignment && $errors->has('reason')) aria-invalid="true" aria-describedby="event-validation-errors" @endif>
                    </div>
                    <button type="submit" class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold">Simpan Konfirmasi</button>
                </form>
            @endif

            <div class="divide-y divide-mk-borderLight">
                @foreach($participants as $participant)
                    @php
                        $participantStatus = $teacherStatuses[$participant->attendance_status] ?? [$participant->attendance_status, 'bg-mk-surface text-mk-text border-mk-border', 'bg-mk-muted'];
                        $canOperateParticipant = $canMutate && $event->isPublished() && $attendanceOpen;
                    @endphp
                    <article class="p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h4 class="font-semibold text-mk-text">{{ $participant->student->full_name }}</h4>
                                <p class="mt-0.5 text-sm text-mk-muted">{{ $participant->snapshot_package_code }} · {{ $participant->snapshot_instrument_name }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $participantStatus[1] }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $participantStatus[2] }}" aria-hidden="true"></span>
                                {{ $participantStatus[0] }}
                            </span>
                        </div>

                        @if($participant->event_notes)
                            <p class="mt-3 rounded-lg bg-mk-surface p-3 text-sm text-mk-text"><span class="font-semibold">Catatan:</span> {{ $participant->event_notes }}</p>
                        @endif

                        @if($canOperateParticipant)
                            @if($participant->attendance_status !== 'PENDING')
                                <form method="POST" action="{{ route('event-participants.attendance.reset', $participant) }}" class="mt-4 flex flex-wrap items-end gap-3">
                                    @csrf
                                    <div class="min-w-[16rem] flex-1">
                                        <label for="reset-reason-{{ $participant->id }}" class="block text-sm font-semibold text-mk-text">Alasan reset absensi</label>
                                        <input id="reset-reason-{{ $participant->id }}" type="text" name="reason" required maxlength="1000" class="mt-1 w-full rounded-md text-sm">
                                    </div>
                                    <button type="submit" class="rounded-lg border border-mk-border px-4 py-2 text-sm font-semibold text-mk-muted hover:bg-mk-surface hover:text-mk-text">Reset Absensi</button>
                                </form>
                                <p class="mt-2 text-sm text-mk-muted">Reset absensi sebelum mengganti guru.</p>
                            @elseif($assignment->attendance_status === 'HADIR')
                                <form method="POST" action="{{ route('event-participants.attendance', $participant) }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)_auto] sm:items-end">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <label for="participant-attendance-{{ $participant->id }}" class="block text-sm font-semibold text-mk-text">Kehadiran murid</label>
                                        <select id="participant-attendance-{{ $participant->id }}" name="attendance_status" required class="mt-1 w-full rounded-md text-sm">
                                            <option value="HADIR">Hadir</option>
                                            <option value="TIDAK_HADIR">Tidak hadir</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="participant-notes-{{ $participant->id }}" class="block text-sm font-semibold text-mk-text">Catatan</label>
                                        <input id="participant-notes-{{ $participant->id }}" type="text" name="event_notes" maxlength="1000" class="mt-1 w-full rounded-md text-sm">
                                    </div>
                                    <button type="submit" class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold">Simpan Absensi</button>
                                </form>
                            @else
                                <p class="mt-3 rounded-lg border border-mk-warningBorder bg-mk-warningBg p-3 text-sm font-semibold text-mk-warning">Guru pendamping harus Hadir sebelum absensi murid diisi.</p>
                            @endif

                            @if($participant->attendance_status === 'PENDING')
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
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="rounded-lg border border-dashed border-mk-border bg-mk-card px-5 py-10 text-center">
            <p class="font-semibold text-mk-text">Belum ada guru pendamping.</p>
            <p class="mt-1 text-sm text-mk-muted">Guru akan muncul setelah peserta memiliki assignment aktif.</p>
        </div>
    @endforelse
</div>
