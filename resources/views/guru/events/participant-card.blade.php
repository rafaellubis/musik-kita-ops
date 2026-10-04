@php
    $statusMeta = match($participant->attendance_status) {
        \App\Models\EventParticipant::ATTENDANCE_HADIR => ['Hadir', 'border-secondary/30 bg-secondary/10 text-secondary'],
        \App\Models\EventParticipant::ATTENDANCE_TIDAK_HADIR => ['Tidak hadir', 'border-error/30 bg-error-container text-on-error-container'],
        default => ['Belum diisi', 'border-yellow-300 bg-yellow-100 text-yellow-900'],
    };
    $lockReasons = [];
    if ($event->isCompleted()) {
        $lockReasons[] = 'Event sudah selesai. Absensi hanya dapat dilihat.';
    }
    if ($event->isPublished() && $event->event_date->isFuture()) {
        $lockReasons[] = 'Absensi dibuka mulai tanggal event.';
    }
    if ($assignment->attendance_status === \App\Models\EventTeacherAssignment::ATTENDANCE_PENDING) {
        $lockReasons[] = 'Menunggu konfirmasi kehadiran guru oleh Admin.';
    } elseif ($assignment->attendance_status === \App\Models\EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR) {
        $lockReasons[] = 'Anda tercatat tidak hadir sebagai guru pendamping.';
    }
    $canUpdate = $event->isPublished()
        && $event->event_date->lte(today())
        && $assignment->attendance_status === \App\Models\EventTeacherAssignment::ATTENDANCE_HADIR;
    $submitted = (int) old('participant_id') === $participant->id;
    $selectedStatus = $submitted ? old('attendance_status') : $participant->attendance_status;
    $notes = $submitted ? old('event_notes') : $participant->event_notes;
@endphp

<article class="rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-4 shadow-[0_1px_4px_rgba(74,31,10,0.08)]">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-base font-bold text-primary">{{ $participant->student->full_name }}</h3>
            <p class="mt-1 text-xs font-semibold text-on-surface-variant">
                {{ $participant->snapshot_instrument_name }}
                <span aria-hidden="true">·</span>
                {{ $participant->snapshot_package_code }}
                @if($participant->snapshot_grade)
                    <span aria-hidden="true">·</span> {{ $participant->snapshot_grade }}
                @endif
            </p>
            <p class="mt-1 text-xs text-on-surface-variant">
                Enrollment #{{ $participant->enrollment_id }} · Estimasi honor Rp {{ number_format($participant->snapshot_honor_amount, 0, ',', '.') }}
            </p>
        </div>
        <span class="rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusMeta[1] }}">{{ $statusMeta[0] }}</span>
    </div>

    @if($canUpdate)
        <form method="POST" action="{{ route('guru.events.attendance.update', $participant) }}" class="mt-4 border-t border-outline-variant/20 pt-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="participant_id" value="{{ $participant->id }}">

            <fieldset>
                <legend class="text-xs font-bold text-primary">Status kehadiran</legend>
                <div class="mt-2 flex flex-wrap gap-4">
                    @foreach([\App\Models\EventParticipant::ATTENDANCE_HADIR => 'Hadir', \App\Models\EventParticipant::ATTENDANCE_TIDAK_HADIR => 'Tidak hadir'] as $value => $label)
                        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold text-on-surface">
                            <input type="radio" name="attendance_status" value="{{ $value }}"
                                   class="border-outline text-secondary focus:ring-secondary"
                                   @checked($selectedStatus === $value || ($selectedStatus === \App\Models\EventParticipant::ATTENDANCE_PENDING && $value === \App\Models\EventParticipant::ATTENDANCE_HADIR))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @if($submitted)
                    @error('attendance_status')
                        <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                    @enderror
                @endif
            </fieldset>

            <label for="event-notes-{{ $participant->id }}" class="mt-3 block text-xs font-bold text-primary">Catatan event <span class="font-normal text-on-surface-variant">(opsional)</span></label>
            <textarea id="event-notes-{{ $participant->id }}" name="event_notes" rows="3" maxlength="1000"
                      class="mt-1 block w-full rounded-lg border-outline-variant text-sm text-on-surface placeholder:text-on-surface-variant focus:border-secondary focus:ring-secondary"
                      placeholder="Catatan khusus penampilan murid pada event ini">{{ $notes }}</textarea>
            @if($submitted)
                @error('event_notes')
                    <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                @enderror
            @endif

            <button type="submit"
                    class="mt-3 min-h-11 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-on-primary transition-colors hover:bg-primary-container focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary focus-visible:ring-offset-2">
                Simpan Kehadiran
            </button>
        </form>
    @else
        <div class="mt-4 rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2.5 text-xs font-semibold text-yellow-900" role="note">
            <p class="font-bold">Kartu terkunci</p>
            <ul class="mt-1 list-disc space-y-1 pl-4">
                @foreach($lockReasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        </div>
        @if($participant->event_notes)
            <div class="mt-3">
                <p class="text-xs font-bold text-primary">Catatan event</p>
                <p class="mt-1 whitespace-pre-line text-sm text-on-surface">{{ $participant->event_notes }}</p>
            </div>
        @endif
    @endif

    @if($participant->attendance_updated_at)
        <p class="mt-3 border-t border-outline-variant/20 pt-3 text-xs text-on-surface-variant">
            Terakhir diperbarui oleh <span class="font-semibold text-on-surface">{{ $participant->attendanceUpdatedBy?->name ?? 'Pengguna terhapus' }}</span>
            · {{ $participant->attendance_updated_at->translatedFormat('d M Y, H:i') }}
        </p>
    @endif
</article>
