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
    $waService = app(\App\Services\SessionReportWaService::class);
    $waState = $teacherNote ? $waService->deliveryState($sesi) : 'DISABLED';
    $waRecipientType = $sesi->student ? $waService->resolveRecipientType($sesi->student) : null;
    $waRecipientLabel = $waRecipientType === 'student' ? 'murid' : 'ortu';
    $waMaskedPhone = $waService->maskPhone(
        $waRecipientType === 'student'
            ? $sesi->student?->phone
            : ($sesi->student?->parent_phone ?: $sesi->student?->phone)
    );
@endphp
<div class="px-4 py-3 bg-surface-container-low/20" x-data="{}">
    <details class="group"
             @if($errors->any() || filled(old('material_learned')) || filled(old('homework_notes')) || filled(old('notes')) || filled(old('session_rating'))) open @endif>
        <summary class="flex items-center justify-between py-2 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
            <div class="flex items-center justify-between flex-1 pr-4">
                <span class="text-sm font-semibold text-primary flex items-center gap-1.5">
                    Catatan Sesi
                    @if(!$teacherNote)
                        <span class="text-[#2c694e] font-normal text-xs ml-1.5 hidden sm:inline">— tap untuk mengisi laporan</span>
                    @endif
                </span>
                
                <!-- Tombol Riwayat Sesi -->
                <button type="button"
                        class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#e8f5e9] text-[#2c694e] hover:bg-[#c8e6c9] hover:text-[#1b5e20] transition-all shrink-0 shadow-sm flex items-center gap-1 border border-[#a5d6a7] cursor-pointer"
                        @click.stop.prevent="$dispatch('open-session-history', { sessionId: {{ $sesi->id }}, studentName: '{{ addslashes($sesi->student->full_name) }}' })">
                    ⏳ Riwayat Sesi
                </button>
            </div>
            <span class="text-xs text-outline group-open:hidden">▼</span>
            <span class="text-xs text-outline hidden group-open:inline">▲</span>
        </summary>
        <div class="space-y-4 pt-2.5">
            <form method="POST" action="{{ route('guru.sesi.catatan.update', $sesi) }}" class="space-y-3">
                @csrf @method('PATCH')
                
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Rating Anak Hari Ini</label>
                    <select name="session_rating"
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
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Materi yang Dipelajari</label>
                    <textarea name="material_learned" rows="2" maxlength="2000"
                              class="w-full border border-outline-variant/35 rounded-xl px-3 py-2 text-sm bg-white
                                     focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary resize-y"
                              placeholder="Contoh: Scales mayor, teknik pernafasan">{{ old('material_learned', $teacherNote?->material_learned) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Tugas &amp; Latihan (1 Minggu Kedepan)</label>
                    <textarea name="homework_notes" rows="2" maxlength="2000"
                              class="w-full border border-outline-variant/35 rounded-xl px-3 py-2 text-sm bg-white
                                     focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary resize-y"
                              placeholder="Contoh: Latihan 15 menit per hari">{{ old('homework_notes', $teacherNote?->homework_notes) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Catatan Tambahan</label>
                    <textarea name="notes" rows="2" maxlength="2000"
                              class="w-full border border-outline-variant/35 rounded-xl px-3 py-2 text-sm bg-white
                                     focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary resize-y"
                              placeholder="Catatan tambahan untuk murid/orang tua">{{ old('notes', $teacherNote?->notes) }}</textarea>
                </div>

                <button type="submit" class="w-full py-2.5 rounded-xl font-bold text-sm bg-[#2a0002] text-white hover:bg-[#4a0e0e] transition-colors shadow-sm cursor-pointer">
                    Simpan Catatan Sesi
                </button>

                @if($waState !== 'DISABLED')
                    <div class="text-xs rounded-xl px-3 py-2.5 border mt-3
                        @if($waState === 'SENT') border-green-200 bg-green-50/70 text-green-700
                        @elseif($waState === 'FAILED') border-red-200 bg-red-50/70 text-red-700
                        @elseif($waState === 'SKIPPED') border-gray-200 bg-gray-50/70 text-gray-600
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
                            <div class="flex items-center gap-1.5 text-gray-500">
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
