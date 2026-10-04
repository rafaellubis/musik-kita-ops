<x-guru-layout title="Detail Event">
    @php
        $assignmentMeta = match($assignment->attendance_status) {
            \App\Models\EventTeacherAssignment::ATTENDANCE_HADIR => ['Guru hadir', 'border-secondary/30 bg-secondary/10 text-secondary'],
            \App\Models\EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR => ['Guru tidak hadir', 'border-error/30 bg-error-container text-on-error-container'],
            default => ['Menunggu konfirmasi guru', 'border-yellow-300 bg-yellow-100 text-yellow-900'],
        };
        $incompleteCount = $participants->where('attendance_status', \App\Models\EventParticipant::ATTENDANCE_PENDING)->count();
    @endphp

    <div class="px-4 py-5 lg:px-6 lg:py-6">
        <a href="{{ route('guru.events.index') }}"
           class="inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm font-semibold text-secondary hover:bg-secondary/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary">
            <span aria-hidden="true">←</span> Kembali ke Event
        </a>

        <header class="mt-3 border-b border-outline-variant/20 pb-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-mono text-xs font-semibold text-on-surface-variant">{{ $event->event_number }}</p>
                    <h1 class="mt-1 text-wrap-balance font-serif text-2xl font-bold tracking-tight text-primary">{{ $event->name }}</h1>
                    <p class="mt-2 text-sm font-semibold text-on-surface">
                        {{ $event->type_label }} <span class="text-outline" aria-hidden="true">·</span>
                        {{ $event->event_date->translatedFormat('l, d F Y') }}
                    </p>
                </div>
                <span class="inline-flex rounded-full border px-3 py-1.5 text-xs font-bold {{ $assignmentMeta[1] }}">
                    {{ $assignmentMeta[0] }}
                </span>
            </div>

            <dl class="mt-5 flex flex-wrap gap-x-8 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold text-on-surface-variant">Estimasi honor</dt>
                    <dd class="mt-0.5 font-bold text-primary">Rp {{ number_format($estimatedHonor, 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-on-surface-variant">Kartu murid</dt>
                    <dd class="mt-0.5 font-bold text-primary">{{ $participants->count() }} total · {{ $incompleteCount }} belum lengkap</dd>
                </div>
            </dl>
        </header>

        <section class="mt-6" aria-labelledby="participant-heading">
            <div class="mb-3">
                <h2 id="participant-heading" class="text-base font-bold text-primary">Kehadiran Murid</h2>
                <p class="mt-1 text-xs text-on-surface-variant">Satu kartu mewakili satu enrollment murid pada event ini.</p>
            </div>

            <div class="space-y-3">
                @foreach($participants as $participant)
                    @include('guru.events.participant-card', [
                        'participant' => $participant,
                        'event' => $event,
                        'assignment' => $assignment,
                    ])
                @endforeach
            </div>
        </section>
    </div>
</x-guru-layout>
