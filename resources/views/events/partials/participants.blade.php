@php
    $participantGroups = $event->participants->groupBy('student_id');
    $participantMutationOpen = $canMutate && ($event->isDraft() || $event->isPublished());
    if ($participantMutationOpen) {
        $availableStudents->load(['enrollments' => fn ($query) => $query->active()->with(['package', 'teacher'])]);
    }
    // Data enrollment per murid: dikirim sebagai JSON supaya Alpine hanya merender pilihan milik murid terpilih.
    $enrollmentPerMurid = $availableStudents->mapWithKeys(fn ($murid) => [
        (string) $murid->id => $murid->enrollments->map(fn ($enrollment) => [
            'id' => $enrollment->id,
            'paket' => $enrollment->package?->code ?? 'Paket tidak tersedia',
            'guru' => $enrollment->teacher?->name ?? 'Guru belum tersedia',
        ])->values()->all(),
    ])->all();
@endphp

{{-- State `removingParticipant` ditaruh di root: dipakai tombol Hapus + baris form alasan (event PUBLISHED). --}}
<div class="space-y-5" x-data="{ removingParticipant: null }">
    @if($participantMutationOpen)
        <div class="rounded-lg border border-mk-borderLight bg-mk-card p-4"
             x-data="{
                 murid: @js((string) old('student_id', '')),
                 dipilih: @js(array_map('intval', (array) old('enrollment_ids', []))),
                 enrollment: @js($enrollmentPerMurid),
             }"
             @searchable-select-changed.window="murid = $event.detail.value; dipilih = []">
            <h3 class="mb-3 text-sm font-semibold text-mk-text">Tambah Peserta</h3>
            <form method="POST" action="{{ route('events.participants.store', $event) }}" aria-label="Tambah peserta event"
                  class="flex flex-wrap items-end gap-1">
                @csrf
                <input type="hidden" name="event_ui_tab" value="participants">

                <div class="w-full max-w-full sm:w-[36rem]">
                    <x-searchable-select name="student_id" label="Murid" placeholder="Cari murid..." required
                        :options="$availableStudents->map(fn ($murid) => ['value' => $murid->id, 'label' => $murid->full_name.' ('.$murid->student_code.')'])->all()" />
                </div>

                <div class="min-w-[240px]">
                    <div class="mb-1 text-xs text-mk-dim">Instrument</div>
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-for="item in (enrollment[murid] ?? [])" :key="item.id">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-mk-border bg-white px-3 py-1.5 text-xs transition-colors hover:border-mk-accent">
                                <input type="checkbox" name="enrollment_ids[]" :value="item.id" x-model.number="dipilih"
                                       class="rounded text-mk-accent focus:ring-mk-accent">
                                <span class="font-semibold text-mk-text" x-text="item.paket"></span>
                                <span class="text-mk-muted" x-text="item.guru"></span>
                            </label>
                        </template>
                        <p x-show="! murid" class="text-xs text-mk-dim">Pilih murid dulu.</p>
                        <p x-show="murid && (enrollment[murid] ?? []).length === 0" x-cloak class="text-xs text-mk-dim">Tidak ada enrollment aktif.</p>
                    </div>
                </div>

                @if($event->isPublished())
                    <div class="w-full sm:w-72">
                        <label for="participant-reason" class="mb-1 block text-xs text-mk-dim">Alasan penambahan <span class="text-red-700">*</span></label>
                        <textarea id="participant-reason" name="reason" required maxlength="1000" rows="1"
                                  class="w-full resize-y rounded text-sm">{{ old('reason') }}</textarea>
                    </div>
                @endif

                <button type="submit" :disabled="dipilih.length === 0"
                        class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-50">Tambah</button>
            </form>
        </div>
    @endif

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
        <div class="overflow-hidden rounded-lg border border-mk-border bg-mk-card">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-sm">
                    <thead class="bg-mk-surface text-left text-xs font-semibold text-mk-muted">
                        <tr class="border-b border-mk-borderLight">
                            <th scope="col" class="px-4 py-3">Nama Murid</th>
                            <th scope="col" class="px-4 py-3">Kelas</th>
                            <th scope="col" class="px-4 py-3">Honor Estimasi</th>
                            <th scope="col" class="px-4 py-3">Guru Pendamping</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3 text-right"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>

                    {{-- Satu <tbody> per murid — penanda grup enrollment dalam satu tabel. --}}
                    @foreach($participantGroups as $studentId => $participants)
                        @php
                            $student = $participants->first()->student;
                        @endphp
                        <tbody data-student-group="{{ $studentId }}" class="divide-y divide-mk-borderLight border-b border-mk-border last:border-b-0">
                            @foreach($participants as $participant)
                                @php
                                    $isActive = $participant->status === 'ACTIVE';
                                    $assignedTeacherId = $participant->activeTeacherAssignment?->teacher_id;
                                    $canRemove = $participantMutationOpen && $isActive;
                                @endphp
                                <tr class="align-top transition-colors hover:bg-mk-surface">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('students.show', $student) }}"
                                           class="font-semibold text-mk-text underline-offset-4 hover:text-mk-muted hover:underline focus:outline-none focus:ring-2 focus:ring-mk-accent">
                                            {{ $student->full_name }}
                                        </a>
                                        <div class="mt-0.5 text-xs text-mk-muted">" {{ $student->nickname ?: '—' }} " . <span class="whitespace-nowrap font-mono">[ {{ $student->student_code }} ]</span></div>
                                    </td>

                                    <td class="px-4 py-3 text-mk-text">
                                        {{ $participant->snapshot_instrument_name }} · {{ $participant->snapshot_class_type }}@if($participant->snapshot_grade) · {{ $participant->snapshot_grade }}@endif
                                        <div class="mt-0.5 text-xs text-mk-muted">{{ $participant->snapshot_package_code }} · {{ $participant->snapshot_duration_min }} menit</div>
                                    </td>

                                    <td class="px-4 py-3 tabular-nums text-mk-text">
                                        Rp {{ number_format($participant->snapshot_honor_amount, 0, ',', '.') }}
                                    </td>

                                    <td class="px-4 py-3">
                                        @if($participantMutationOpen && $event->isDraft() && $isActive)
                                            <form method="POST" action="{{ route('event-participants.update-teacher', $participant) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="event_ui_tab" value="participants">
                                                <label for="teacher-{{ $participant->id }}" class="sr-only">Guru pendamping {{ $student->full_name }} untuk {{ $participant->snapshot_package_code }}</label>
                                                <select id="teacher-{{ $participant->id }}" name="teacher_id" required onchange="this.form.submit()" class="w-full rounded-md text-sm">
                                                    <option value="">Pilih guru</option>
                                                    @foreach($activeTeachers as $teacher)
                                                        <option value="{{ $teacher->id }}" @selected($assignedTeacherId === $teacher->id)>{{ $teacher->name }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @else
                                            <span class="font-semibold text-mk-text">{{ $participant->activeTeacherAssignment?->teacher?->name ?? 'Belum ditentukan' }}</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $isActive ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $isActive ? 'bg-emerald-600' : 'bg-red-600' }}" aria-hidden="true"></span>
                                            {{ $isActive ? 'Aktif' : 'Dibatalkan' }}
                                        </span>
                                        @if(!$isActive && $participant->cancellation_reason)
                                            <p class="mt-1 max-w-[16rem] text-xs text-red-900"><span class="font-semibold">Alasan:</span> {{ $participant->cancellation_reason }}</p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        @if($canRemove)
                                            @if($event->isDraft())
                                                <form method="POST" action="{{ route('event-participants.destroy', $participant) }}" onsubmit="return confirm('Hapus keikutsertaan enrollment ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="event_ui_tab" value="participants">
                                                    <button type="submit" aria-label="Hapus {{ $student->full_name }} dari {{ $participant->snapshot_package_code }}" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-900 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Hapus</button>
                                                </form>
                                            @else
                                                <button type="button"
                                                        @click="removingParticipant = (removingParticipant === {{ $participant->id }} ? null : {{ $participant->id }})"
                                                        :aria-expanded="(removingParticipant === {{ $participant->id }}).toString()"
                                                        aria-controls="remove-participant-{{ $participant->id }}"
                                                        aria-label="Hapus {{ $student->full_name }} dari {{ $participant->snapshot_package_code }}"
                                                        class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-900 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Hapus</button>
                                            @endif
                                        @endif
                                    </td>
                                </tr>

                                {{-- Form alasan pembatalan: baris penuh di dalam grup murid yang sama. --}}
                                @if($canRemove && $event->isPublished())
                                    <tr id="remove-participant-{{ $participant->id }}" x-show="removingParticipant === {{ $participant->id }}" x-cloak class="bg-mk-surface">
                                        <td colspan="6" class="px-4 py-3">
                                            <form method="POST" action="{{ route('event-participants.destroy', $participant) }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="event_ui_tab" value="participants">
                                                <p class="text-sm text-mk-muted">Keikutsertaan ini akan dibatalkan. Invoice tanpa pembayaran disesuaikan atau di-void jika tidak ada item tersisa; invoice dengan pembayaran tetap tersimpan.</p>
                                                <label for="cancel-participant-{{ $participant->id }}" class="mt-3 block text-sm font-semibold text-mk-text">Alasan pembatalan</label>
                                                <textarea id="cancel-participant-{{ $participant->id }}" name="cancellation_reason" required maxlength="1000" rows="2" class="mt-1 w-full rounded-md"></textarea>
                                                <div class="mt-2 flex justify-end gap-2">
                                                    <button type="button" @click="removingParticipant = null" class="rounded-md px-3 py-2 text-sm font-semibold text-mk-muted hover:bg-mk-cardHover">Batal</button>
                                                    <button type="submit" class="rounded-md bg-red-800 px-3 py-2 text-sm font-bold text-white hover:bg-red-900">Batalkan Peserta</button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
    @endif
</div>
