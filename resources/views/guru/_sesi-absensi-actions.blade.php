{{--
    Tombol absensi portal guru — dipakai di dashboard & jadwal.
    $sesi: ClassSession, $teacher: Teacher (guru login)
--}}
@php
    $isSubstitutePending = (int) $sesi->substitute_teacher_id === (int) $teacher->id
        && $sesi->status === 'DIGANTI'
        && $sesi->honor_code === null;
    $isSubstituteConfirmed = (int) $sesi->substitute_teacher_id === (int) $teacher->id
        && $sesi->status === 'DIGANTI'
        && $sesi->honor_code !== null;
    $isOwnScheduled = $sesi->status === 'SCHEDULED'
        && (int) $sesi->teacher_id === (int) $teacher->id
        && !$sesi->substitute_teacher_id;
    $canWriteNotes = (
        in_array($sesi->status, ['HADIR', 'HADIR_TERLAMBAT'], true)
        && (int) $sesi->teacher_id === (int) $teacher->id
    ) || $isSubstituteConfirmed;
    $teacherNote = $sesi->teacherNote;
    $waDisplay = $waDisplayMap[$sesi->id] ?? [
        'state' => 'DISABLED',
        'recipient_label' => 'ortu',
        'masked_phone' => '—',
    ];
    $waState = $waDisplay['state'];
    $waRecipientLabel = $waDisplay['recipient_label'];
    $waMaskedPhone = $waDisplay['masked_phone'];
@endphp
<div class="px-4 py-3 bg-surface-container-low/20" x-data="{}">
    <details class="group relative"
             @if($errors->any() || filled(old('material_learned')) || filled(old('homework_notes')) || filled(old('notes')) || filled(old('session_rating'))) open @endif>
        <summary class="flex items-center justify-between py-2 pr-36 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
            <span class="text-sm font-semibold text-primary flex items-center gap-1.5">
                Catatan Sesi
                @if(!$teacherNote)
                    <span class="text-secondary font-normal text-xs ml-1.5 hidden sm:inline">— tap untuk mengisi laporan</span>
                @endif
            </span>
            <span class="text-xs text-outline group-open:hidden">▼</span>
            <span class="text-xs text-outline hidden group-open:inline">▲</span>
        </summary>
        <button type="button"
                class="absolute right-0 top-2 px-2.5 py-1 rounded-full text-[11px] font-bold bg-secondary-container/40 text-secondary hover:bg-secondary-container/70 hover:text-on-secondary-container transition-all shrink-0 shadow-sm flex items-center gap-1 border border-secondary/30 cursor-pointer"
                @click="$dispatch('open-session-history', {
                    sessionId: {{ $sesi->id }},
                    studentName: '{{ addslashes($sesi->student->full_name) }}',
                    sessionDate: '{{ $sesi->session_date }}'
                })">
            ⏳ Riwayat Sesi
        </button>
        <div class="space-y-4 pt-2.5">
            <form method="POST" action="{{ route('guru.sesi.catatan.update', $sesi) }}" class="space-y-3">
                @csrf @method('PATCH')
                
                <div>
                    <label for="session_rating-{{ $sesi->id }}" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Rating Anak Hari Ini</label>
                    <select id="session_rating-{{ $sesi->id }}" name="session_rating"
                            class="w-full border border-outline-variant/35 rounded-xl px-3 py-2 text-sm bg-white
                                   focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                        <option value="">— Pilih rating (opsional) —</option>
                        @for ($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}" @selected((int) old('session_rating', $teacherNote?->session_rating) === $i)>
                                {{ $i }} / 5 ({{ str_repeat('★', $i) }}{{ str_repeat('☆', 5 - $i) }})
                            </option>
                        @endfor
                    </select>
                </div>

                <div>
                    <label for="material_learned-{{ $sesi->id }}" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Materi yang Dipelajari</label>
                    <textarea id="material_learned-{{ $sesi->id }}" name="material_learned" rows="2" maxlength="2000"
                              class="w-full border border-outline-variant/35 rounded-xl px-3 py-2 text-sm bg-white
                                     focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary resize-y"
                              placeholder="Contoh: Scales mayor, teknik pernafasan">{{ old('material_learned', $teacherNote?->material_learned) }}</textarea>
                </div>

                <div>
                    <label for="homework_notes-{{ $sesi->id }}" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Tugas &amp; Latihan (1 Minggu Kedepan)</label>
                    <textarea id="homework_notes-{{ $sesi->id }}" name="homework_notes" rows="2" maxlength="2000"
                              class="w-full border border-outline-variant/35 rounded-xl px-3 py-2 text-sm bg-white
                                     focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary resize-y"
                              placeholder="Contoh: Latihan 15 menit per hari">{{ old('homework_notes', $teacherNote?->homework_notes) }}</textarea>
                </div>

                <div>
                    <label for="notes-{{ $sesi->id }}" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Catatan Tambahan</label>
                    <textarea id="notes-{{ $sesi->id }}" name="notes" rows="2" maxlength="2000"
                              class="w-full border border-outline-variant/35 rounded-xl px-3 py-2 text-sm bg-white
                                     focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary resize-y"
                              placeholder="Catatan tambahan untuk murid/orang tua">{{ old('notes', $teacherNote?->notes) }}</textarea>
                </div>

                <button type="submit" class="w-full py-2.5 rounded-xl font-bold text-sm bg-primary text-on-primary hover:bg-primary-container transition-colors shadow-sm cursor-pointer">
                    Simpan Catatan Sesi
                </button>

                @if($waState !== 'DISABLED')
                    <div class="text-xs rounded-xl px-3 py-2.5 border mt-3
                        @if($waState === 'SENT') border-green-200 bg-green-50/70 text-green-700
                        @elseif($waState === 'FAILED') border-red-200 bg-red-50/70 text-red-700
                        @elseif($waState === 'SKIPPED') border-outline-variant/30 bg-surface-container-low/70 text-on-surface-variant
                        @else border-amber-200 bg-amber-50/70 text-amber-700
                        @endif">
                        @if($waState === 'SENT')
                            <div class="flex items-center gap-1.5 font-medium">
                                <span>✓</span>
                                <span>Laporan terkirim via WA ke {{ $waRecipientLabel }} ({{ $waMaskedPhone }})</span>
                            </div>
                        @elseif($waState === 'FAILED')
                            <div class="flex items-center gap-1.5 font-medium text-red-800">
                                <span>⚠</span>
                                <span>Gagal kirim WA — hubungi admin</span>
                            </div>
                        @elseif($waState === 'SKIPPED')
                            <div class="flex items-center gap-1.5 text-on-surface-variant/70">
                                <span>ℹ</span>
                                <span>Nomor WA tidak tersedia (pesan dilewati)</span>
                            </div>
                        @else
                            <div class="flex items-center gap-1.5 font-medium text-amber-800">
                                <span class="animate-pulse">⏳</span>
                                <span>Akan dikirim ke {{ $waRecipientLabel }} dlm ~{{ config('session_report_wa.debounce_minutes', 10) }} mnt</span>
                            </div>
                        @endif
                    </div>
                @endif
            </form>
        </div>
    </details>
</div>
