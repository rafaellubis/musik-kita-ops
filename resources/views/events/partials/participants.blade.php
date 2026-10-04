@php
    $participantGroups = $event->participants->groupBy('student_id');
    $participantMutationOpen = $canMutate && ($event->isDraft() || $event->isPublished());
    if ($participantMutationOpen) {
        $availableStudents->load(['enrollments' => fn ($query) => $query->active()->with(['package', 'teacher'])]);
    }
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-mk-text">Peserta event</h2>
            <p class="mt-1 text-sm text-mk-muted">Satu murid dapat membawa beberapa enrollment ke event yang sama.</p>
        </div>
        <div class="text-sm font-semibold text-mk-text">{{ $participantGroups->count() }} murid · {{ $event->participants->count() }} enrollment</div>
    </div>

    @if($participantGroups->isEmpty())
        <div class="rounded-lg border border-dashed border-mk-border bg-mk-card px-5 py-10 text-center">
            <p class="font-semibold text-mk-text">Belum ada peserta.</p>
            <p class="mt-1 text-sm text-mk-muted">Tambahkan murid beserta enrollment yang akan tampil.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($participantGroups as $studentId => $participants)
                @php
                    $student = $participants->first()->student;
                @endphp
                <section data-student-group="{{ $studentId }}" class="overflow-hidden rounded-lg border border-mk-borderLight bg-mk-card">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-mk-borderLight bg-mk-surface px-4 py-3">
                        <div>
                            <a href="{{ route('students.show', $student) }}" class="font-semibold text-mk-text underline-offset-4 hover:text-mk-muted hover:underline">
                                {{ $student->full_name }}
                            </a>
                            <span class="ml-2 font-mono text-xs text-mk-muted">{{ $student->student_code }}</span>
                        </div>
                        <span class="text-xs font-semibold text-mk-muted">{{ $participants->count() }} enrollment</span>
                    </div>

                    <div class="grid gap-3 p-4 lg:grid-cols-2">
                        @foreach($participants as $participant)
                            @php
                                $isActive = $participant->status === 'ACTIVE';
                                $assignedTeacherId = $participant->activeTeacherAssignment?->teacher_id;
                            @endphp
                            <article class="rounded-lg border border-mk-borderLight p-4 {{ $isActive ? 'bg-white' : 'bg-red-50/60' }}">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <h3 class="font-semibold text-mk-text">{{ $participant->snapshot_package_code }}</h3>
                                        <p class="mt-0.5 text-sm text-mk-muted">
                                            {{ $participant->snapshot_instrument_name }} · {{ $participant->snapshot_class_type }}
                                            @if($participant->snapshot_grade) · {{ $participant->snapshot_grade }} @endif
                                            · {{ $participant->snapshot_duration_min }} menit
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $isActive ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $isActive ? 'bg-emerald-600' : 'bg-red-600' }}" aria-hidden="true"></span>
                                        {{ $isActive ? 'Aktif' : 'Dibatalkan' }}
                                    </span>
                                </div>

                                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt class="text-xs font-semibold text-mk-muted">Harga paket</dt>
                                        <dd class="mt-0.5 font-mono text-mk-text">Rp {{ number_format($participant->snapshot_package_price, 0, ',', '.') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold text-mk-muted">Honor estimasi</dt>
                                        <dd class="mt-0.5 font-mono text-mk-text">Rp {{ number_format($participant->snapshot_honor_amount, 0, ',', '.') }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-4 border-t border-mk-borderLight pt-3">
                                    <div class="text-xs font-semibold text-mk-muted">Guru pendamping</div>
                                    @if($participantMutationOpen && $event->isDraft() && $isActive)
                                        <form method="POST" action="{{ route('event-participants.update-teacher', $participant) }}" class="mt-1.5">
                                            @csrf
                                            @method('PATCH')
                                            <label for="teacher-{{ $participant->id }}" class="sr-only">Guru pendamping {{ $student->full_name }} untuk {{ $participant->snapshot_package_code }}</label>
                                            <select id="teacher-{{ $participant->id }}" name="teacher_id" required onchange="this.form.submit()" class="w-full rounded-md text-sm">
                                                <option value="">Pilih guru</option>
                                                @foreach($activeTeachers as $teacher)
                                                    <option value="{{ $teacher->id }}" @selected($assignedTeacherId === $teacher->id)>{{ $teacher->name }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <p class="mt-1 text-sm font-semibold text-mk-text">{{ $participant->activeTeacherAssignment?->teacher?->name ?? 'Belum ditentukan' }}</p>
                                    @endif
                                </div>

                                @if(!$isActive && $participant->cancellation_reason)
                                    <p class="mt-3 text-sm text-red-900"><span class="font-semibold">Alasan:</span> {{ $participant->cancellation_reason }}</p>
                                @endif

                                @if($participantMutationOpen && $isActive)
                                    <details class="mt-3 border-t border-mk-borderLight pt-3">
                                        <summary class="cursor-pointer text-sm font-semibold text-red-800">Batalkan keikutsertaan</summary>
                                        <form method="POST" action="{{ route('event-participants.destroy', $participant) }}" class="mt-3">
                                            @csrf
                                            @method('DELETE')
                                            <label for="cancel-participant-{{ $participant->id }}" class="block text-sm font-semibold text-mk-text">Alasan pembatalan</label>
                                            <textarea id="cancel-participant-{{ $participant->id }}" name="cancellation_reason" required maxlength="1000" rows="2" class="mt-1 w-full rounded-md"></textarea>
                                            <button type="submit" class="mt-2 rounded-lg bg-red-800 px-3 py-2 text-sm font-bold text-white hover:bg-red-900">Batalkan Peserta</button>
                                        </form>
                                    </details>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    @if($participantMutationOpen)
        <section class="rounded-lg border border-mk-borderLight bg-mk-card p-5" x-data="{ student: '{{ old('student_id', '') }}' }">
            <h2 class="font-semibold text-mk-text">Tambah Peserta</h2>
            <p class="mt-1 text-sm text-mk-muted">Pilih murid, lalu tandai satu atau beberapa enrollment aktif.</p>

            <form method="POST" action="{{ route('events.participants.store', $event) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="participant-student" class="block text-sm font-semibold text-mk-text">Murid <span class="text-red-700">*</span></label>
                    <select id="participant-student" name="student_id" x-model="student" required class="mt-1.5 w-full rounded-md sm:max-w-xl">
                        <option value="">Pilih murid</option>
                        @foreach($availableStudents as $student)
                            <option value="{{ $student->id }}">{{ $student->full_name }} ({{ $student->student_code }})</option>
                        @endforeach
                    </select>
                    @error('student_id')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                @foreach($availableStudents as $student)
                    <fieldset x-show="student === '{{ $student->id }}'" x-cloak class="rounded-lg border border-mk-borderLight bg-mk-surface p-4">
                        <legend class="px-1 text-sm font-semibold text-mk-text">Enrollment aktif {{ $student->full_name }}</legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @forelse($student->enrollments as $enrollment)
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-mk-borderLight bg-white p-3 text-sm hover:border-mk-border">
                                    <input type="checkbox" name="enrollment_ids[]" value="{{ $enrollment->id }}"
                                           :disabled="student !== '{{ $student->id }}'" class="mt-0.5 rounded text-mk-accent focus:ring-mk-accent">
                                    <span>
                                        <span class="block font-semibold text-mk-text">{{ $enrollment->package?->code ?? 'Paket tidak tersedia' }}</span>
                                        <span class="mt-0.5 block text-mk-muted">{{ $enrollment->teacher?->name ?? 'Guru belum tersedia' }}</span>
                                    </span>
                                </label>
                            @empty
                                <p class="text-sm text-mk-muted">Tidak ada enrollment aktif.</p>
                            @endforelse
                        </div>
                    </fieldset>
                @endforeach
                @error('enrollment_ids')<p class="text-sm font-medium text-red-700">{{ $message }}</p>@enderror

                @if($event->isPublished())
                    <div class="sm:max-w-xl">
                        <label for="participant-reason" class="block text-sm font-semibold text-mk-text">Alasan penambahan <span class="text-red-700">*</span></label>
                        <textarea id="participant-reason" name="reason" required maxlength="1000" rows="2" class="mt-1.5 w-full rounded-md">{{ old('reason') }}</textarea>
                        @error('reason')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endif

                <button type="submit" class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold">Tambah Peserta</button>
            </form>
        </section>
    @endif
</div>
