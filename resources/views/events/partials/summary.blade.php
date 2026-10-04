@php
    $activeParticipants = $event->participants->where('status', 'ACTIVE');
    $assignments = $activeParticipants->pluck('activeTeacherAssignment')->filter()->unique('id')->values();
    $attendanceStarted = $event->teacherAssignments()
        ->where(fn ($query) => $query->whereNotNull('confirmed_at')->orWhere('attendance_status', '!=', 'PENDING'))
        ->exists()
        || $event->participants->contains(fn ($participant) => $participant->attendance_updated_at
            || $participant->attendance_status !== 'PENDING');
    $completionIssues = collect()
        ->when($activeParticipants->isEmpty(), fn ($issues) => $issues->push('Tambahkan minimal satu peserta aktif.'))
        ->when($assignments->contains('attendance_status', 'PENDING'), fn ($issues) => $issues->push('Konfirmasi kehadiran seluruh guru pendamping.'))
        ->when($activeParticipants->contains('attendance_status', 'PENDING'), fn ($issues) => $issues->push('Selesaikan absensi seluruh peserta aktif.'));
    $completionRouteAvailable = \Illuminate\Support\Facades\Route::has('events.complete');
@endphp

<div class="space-y-5">
    <div class="rounded-lg border border-mk-borderLight bg-mk-card p-5">
        <dl class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold text-mk-muted">Tanggal event</dt>
                <dd class="mt-1 text-sm font-semibold text-mk-text">{{ $event->event_date->translatedFormat('d F Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-mk-muted">Batas pembayaran</dt>
                <dd class="mt-1 text-sm font-semibold text-mk-text">{{ $event->payment_due_date->translatedFormat('d F Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-mk-muted">Murid aktif</dt>
                <dd class="mt-1 text-sm font-semibold text-mk-text">{{ $activeParticipants->pluck('student_id')->unique()->count() }} murid</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-mk-muted">Enrollment aktif</dt>
                <dd class="mt-1 text-sm font-semibold text-mk-text">{{ $activeParticipants->count() }} enrollment</dd>
            </div>
        </dl>

        <div class="mt-5 border-t border-mk-borderLight pt-4">
            <h2 class="text-sm font-semibold text-mk-text">Catatan operasional</h2>
            <p class="mt-1 max-w-3xl whitespace-pre-line text-sm text-mk-muted">{{ $event->notes ?: 'Belum ada catatan.' }}</p>
        </div>

        @if($event->isCancelled() && $event->cancellation_reason)
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                <span class="font-semibold">Alasan dibatalkan:</span> {{ $event->cancellation_reason }}
            </div>
        @endif
    </div>

    @if($canMutate && $event->isDraft())
        <div class="rounded-lg border border-mk-borderLight bg-mk-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-mk-text">Persiapan event</h2>
                    <p class="mt-1 max-w-2xl text-sm text-mk-muted">Periksa peserta dan guru pendamping sebelum mempublikasikan. Publikasi akan membuat tagihan peserta.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('events.edit', $event) }}" class="rounded-lg border border-mk-border px-4 py-2 text-sm font-semibold text-mk-muted hover:bg-mk-surface hover:text-mk-text">Edit Event</a>
                    <form method="POST" action="{{ route('events.publish', $event) }}" onsubmit="return confirm('Publikasikan event dan buat tagihan peserta?')">
                        @csrf
                        <button type="submit" class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold">Publikasikan Event</button>
                    </form>
                </div>
            </div>

            <div class="mt-5 border-t border-mk-borderLight pt-4">
                <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Hapus event Draft ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-red-800 underline-offset-4 hover:underline">Hapus event Draft</button>
                </form>
            </div>
        </div>
    @elseif($canMutate && $event->isPublished())
        <div class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-lg border border-mk-borderLight bg-mk-card p-5">
                <h2 class="font-semibold text-mk-text">Jadwal dan pembatalan</h2>
                @if(!$attendanceStarted)
                    <details class="mt-4 rounded-lg border border-mk-borderLight bg-mk-surface p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-mk-text">Jadwalkan ulang event</summary>
                        <form method="POST" action="{{ route('events.reschedule', $event) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                            @csrf
                            @method('PATCH')
                            <div class="sm:col-span-2">
                                <label for="reschedule-name" class="block text-sm font-semibold text-mk-text">Nama event</label>
                                <input id="reschedule-name" type="text" name="name" value="{{ $event->name }}" required maxlength="100" class="mt-1 w-full rounded-md">
                            </div>
                            <div>
                                <label for="reschedule-date" class="block text-sm font-semibold text-mk-text">Tanggal event</label>
                                <input id="reschedule-date" type="date" name="event_date" value="{{ $event->event_date->format('Y-m-d') }}" required class="mt-1 w-full rounded-md">
                            </div>
                            <div>
                                <label for="reschedule-due" class="block text-sm font-semibold text-mk-text">Batas pembayaran</label>
                                <input id="reschedule-due" type="date" name="payment_due_date" value="{{ $event->payment_due_date->format('Y-m-d') }}" required class="mt-1 w-full rounded-md">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="reschedule-notes" class="block text-sm font-semibold text-mk-text">Catatan</label>
                                <textarea id="reschedule-notes" name="notes" rows="2" maxlength="1000" class="mt-1 w-full rounded-md">{{ $event->notes }}</textarea>
                            </div>
                            <button type="submit" class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold sm:col-span-2 sm:justify-self-start">Simpan Jadwal Baru</button>
                        </form>
                    </details>
                @else
                    <p class="mt-3 text-sm text-mk-muted">Jadwal terkunci karena konfirmasi guru atau absensi murid sudah dimulai.</p>
                @endif

                <details class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-red-900">Batalkan Event</summary>
                    <form method="POST" action="{{ route('events.cancel', $event) }}" class="mt-3">
                        @csrf
                        <label for="event-cancellation-reason" class="block text-sm font-semibold text-red-900">Alasan pembatalan</label>
                        <textarea id="event-cancellation-reason" name="cancellation_reason" required maxlength="1000" rows="3" class="mt-1 w-full rounded-md"></textarea>
                        <button type="submit" class="mt-3 rounded-lg bg-red-800 px-4 py-2 text-sm font-bold text-white hover:bg-red-900">Konfirmasi Pembatalan</button>
                    </form>
                </details>
            </div>

            <div class="rounded-lg border border-mk-borderLight bg-mk-card p-5">
                <h2 class="font-semibold text-mk-text">Penyelesaian event</h2>
                <p class="mt-1 text-sm text-mk-muted">Event dapat diselesaikan setelah seluruh kehadiran tercatat.</p>

                @if($completionIssues->isNotEmpty())
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-mk-text">
                        @foreach($completionIssues as $issue)
                            <li>{{ $issue }}</li>
                        @endforeach
                    </ul>
                @endif

                @if(!$completionRouteAvailable)
                    <p class="mt-3 rounded-lg border border-mk-warningBorder bg-mk-warningBg p-3 text-sm font-semibold text-mk-warning">Aksi penyelesaian belum tersedia pada tahap ini.</p>
                @endif

                @if($completionRouteAvailable && $completionIssues->isEmpty())
                    <form method="POST" action="{{ route('events.complete', $event) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold">Tandai Selesai</button>
                    </form>
                @else
                    <button type="button" disabled class="mt-4 cursor-not-allowed rounded-lg bg-mk-surface px-4 py-2 text-sm font-bold text-mk-muted opacity-60">Tandai Selesai</button>
                @endif
            </div>
        </div>
    @elseif(in_array($event->status, ['COMPLETED', 'CANCELLED'], true))
        <div class="rounded-lg border border-mk-borderLight bg-mk-surface p-4 text-sm text-mk-text">
            Event {{ $event->isCompleted() ? 'telah selesai' : 'telah dibatalkan' }}. Data operasional hanya dapat dilihat.
        </div>
    @endif
</div>
