<x-guru-layout title="Event">
    <div class="px-4 py-5 lg:px-6 lg:py-6">
        <div class="mb-6">
            <h1 class="font-serif text-2xl font-bold tracking-tight text-primary">Event Saya</h1>
            <p class="mt-1 max-w-2xl text-sm text-on-surface-variant">
                Daftar Mini Concert dan event lain yang menugaskan Anda sebagai guru pendamping.
            </p>
        </div>

        @php
            $sections = [
                ['title' => 'Event Mendatang', 'description' => 'Event terbit yang belum dimulai.', 'events' => $upcomingEvents, 'empty' => 'Belum ada event mendatang.'],
                ['title' => 'Perlu Absensi', 'description' => 'Event hari ini atau sebelumnya. Buka detail untuk melihat status setiap murid.', 'events' => $actionEvents, 'empty' => 'Tidak ada event yang perlu ditinjau.'],
                ['title' => 'Riwayat Selesai', 'description' => 'Event selesai tersedia sebagai arsip baca-saja.', 'events' => $completedEvents, 'empty' => 'Belum ada riwayat event selesai.'],
            ];
        @endphp

        <div class="space-y-8">
            @foreach($sections as $section)
                <section aria-labelledby="section-{{ $loop->index }}">
                    <div class="mb-3 flex items-end justify-between gap-3 border-b border-outline-variant/20 pb-2">
                        <div>
                            <h2 id="section-{{ $loop->index }}" class="text-base font-bold text-primary">{{ $section['title'] }}</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">{{ $section['description'] }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-secondary">{{ $section['events']->count() }} event</span>
                    </div>

                    @if($section['events']->isEmpty())
                        <p class="rounded-lg border border-outline-variant/20 bg-surface-container-lowest px-4 py-5 text-sm text-on-surface-variant">
                            {{ $section['empty'] }}
                        </p>
                    @else
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach($section['events'] as $event)
                                @php
                                    $assignment = $event->teacherAssignments->first();
                                    $participants = $assignment?->participants ?? collect();
                                    $incomplete = $participants->where('attendance_status', \App\Models\EventParticipant::ATTENDANCE_PENDING)->count();
                                    $assignmentLabel = match($assignment?->attendance_status) {
                                        \App\Models\EventTeacherAssignment::ATTENDANCE_HADIR => 'Guru hadir',
                                        \App\Models\EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR => 'Guru tidak hadir',
                                        default => 'Menunggu konfirmasi guru',
                                    };
                                @endphp
                                <a href="{{ route('guru.events.show', $event) }}"
                                   class="group rounded-xl border border-outline-variant/20 bg-surface-container-lowest p-4 shadow-[0_1px_4px_rgba(74,31,10,0.08)] transition-colors hover:border-secondary/40 hover:bg-surface-container-low focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary focus-visible:ring-offset-2">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="truncate text-sm font-bold text-primary group-hover:text-primary-container">{{ $event->name }}</h3>
                                            <p class="mt-1 text-xs font-medium text-on-surface-variant">
                                                {{ $event->event_date->translatedFormat('d F Y') }}
                                            </p>
                                        </div>
                                        <span class="rounded-full border px-2 py-1 text-[10px] font-bold {{ $event->isCompleted() ? 'border-secondary/25 bg-secondary/10 text-secondary' : 'border-outline-variant/40 bg-surface-container text-primary' }}">
                                            {{ $event->isCompleted() ? 'Selesai' : 'Terbit' }}
                                        </span>
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                        <span class="rounded-md bg-secondary-container/40 px-2 py-1 font-semibold text-on-secondary-container">
                                            {{ $participants->count() }} murid
                                        </span>
                                        <span class="rounded-md bg-surface-container px-2 py-1 font-semibold text-on-surface-variant">
                                            {{ $incomplete }} {{ Str::plural('kartu', $incomplete) }} belum lengkap
                                        </span>
                                    </div>
                                    <p class="mt-3 text-xs font-medium text-on-surface-variant">{{ $assignmentLabel }}</p>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</x-guru-layout>
