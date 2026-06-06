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
@endphp

@if($isSubstitutePending)
    <div class="px-4 py-3 space-y-2">
        <p class="text-xs text-blue-600">
            Anda ditugaskan menggantikan {{ $sesi->teacher->name ?? 'guru utama' }}.
            Konfirmasi setelah sesi selesai.
        </p>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('guru.absensi.confirm-substitute', $sesi) }}" class="flex-1">
                @csrf
                <input type="hidden" name="action" value="hadir">
                <button type="submit"
                        class="w-full py-2.5 rounded-xl font-semibold text-sm appearance-none"
                        style="background-color:#22c55e;color:#ffffff;">
                    ✓ Hadir
                </button>
            </form>
            <form method="POST" action="{{ route('guru.absensi.confirm-substitute', $sesi) }}"
                  onsubmit="return confirm('Batalkan penugasan pengganti? Admin perlu atur ulang.');">
                @csrf
                <input type="hidden" name="action" value="batal">
                <button type="submit"
                        class="w-full py-2.5 rounded-xl font-semibold text-sm appearance-none"
                        style="background-color:rgba(248,113,113,0.15);color:#F87171;">
                    ✗ Batal
                </button>
            </form>
        </div>
    </div>
@elseif($isSubstituteConfirmed)
    <div class="px-4 py-3 flex items-center gap-2">
        @include('guru._badge-status', ['status' => $sesi->status])
        <span class="text-xs text-mk-muted italic">Kehadiran pengganti sudah dikonfirmasi ✓</span>
    </div>
@elseif($isOwnScheduled)
    <div x-data="{ showLate: false }" class="px-4 py-3 space-y-2">
        <div class="flex gap-2">
            <form method="POST" action="{{ route('guru.absensi.update', $sesi) }}" class="flex-1">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="HADIR">
                <button type="submit"
                        class="w-full py-2.5 rounded-xl font-semibold text-sm transition-colors appearance-none"
                        style="background-color:#22c55e;color:#ffffff;">
                    ✓ Hadir
                </button>
            </form>
            <button @click="showLate = !showLate"
                    class="flex-1 py-2.5 rounded-xl border border-yellow-400 text-yellow-600
                           font-semibold text-sm hover:bg-yellow-50 transition-colors appearance-none">
                ⏱ Terlambat
            </button>
        </div>
        <div x-show="showLate" x-transition class="pt-1">
            <form method="POST" action="{{ route('guru.absensi.update', $sesi) }}" class="flex gap-2">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="HADIR_TERLAMBAT">
                <div class="flex-1">
                    <input type="number" name="late_minutes" min="1" max="60"
                           placeholder="Berapa menit terlambat?"
                           class="w-full border border-mk-borderLight rounded-xl px-3 py-2.5 text-sm bg-mk-card
                                  focus:outline-none focus:ring-2 focus:ring-yellow-300">
                </div>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl font-semibold text-sm transition-colors appearance-none"
                        style="background-color:#eab308;color:#ffffff;">
                    Simpan
                </button>
            </form>
        </div>
    </div>
@else
    <div class="px-4 py-3 flex items-center gap-2">
        @include('guru._badge-status', ['status' => $sesi->status])
        <span class="text-xs text-mk-muted italic">
            @if($sesi->status === 'LIBUR')
                Sesi libur — tidak perlu absensi.
            @elseif(in_array($sesi->status, ['HADIR', 'HADIR_TERLAMBAT']))
                Absensi sudah tercatat.
            @elseif($sesi->status === 'DIGANTI' && $sesi->substitute_teacher_id)
                Penugasan pengganti: {{ $sesi->substituteTeacher?->name ?? '?' }}
                @if($sesi->honor_code === null) (menunggu konfirmasi pengganti) @else ✓ @endif
            @else
                Status: {{ $sesi->status }}
            @endif
        </span>
    </div>
@endif

@if($canWriteNotes)
    <div class="px-4 pb-3 border-t border-mk-borderLight">
        <details class="group"
                 @if($errors->any() || filled(old('material_learned')) || filled(old('homework_notes')) || filled(old('notes')) || filled(old('session_rating'))) open @endif>
            <summary class="flex items-center justify-between py-2.5 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                <span class="text-sm font-semibold text-mk-text">
                    Catatan Sesi
                    @if(!$teacherNote)
                        <span class="text-mk-accent font-normal text-xs">— tap untuk isi</span>
                    @endif
                </span>
                <span class="text-xs text-mk-muted group-open:hidden">▼</span>
                <span class="text-xs text-mk-muted hidden group-open:inline">▲</span>
            </summary>
            <div class="space-y-3 pt-1">
                <form method="POST" action="{{ route('guru.sesi.catatan.update', $sesi) }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <div>
                        <label class="block text-xs font-medium text-mk-muted mb-1">Rating Anak hari Ini</label>
                        <select name="session_rating"
                                class="w-full border border-mk-borderLight rounded-xl px-3 py-2.5 text-sm bg-mk-card
                                       focus:outline-none focus:ring-2 focus:ring-mk-accent/30">
                            <option value="">— Pilih rating (opsional) —</option>
                            @for ($i = 1; $i <= 5; $i++)
                                <option value="{{ $i }}" @selected((int) old('session_rating', $teacherNote?->session_rating) === $i)>
                                    {{ $i }} / 5
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-mk-muted mb-1">Materi yang dipelajari</label>
                        <textarea name="material_learned" rows="2" maxlength="2000"
                                  class="w-full border border-mk-borderLight rounded-xl px-3 py-2.5 text-sm bg-mk-card
                                         focus:outline-none focus:ring-2 focus:ring-mk-accent/30 resize-y"
                                  placeholder="Contoh: Scales mayor, teknik pernafasan">{{ old('material_learned', $teacherNote?->material_learned) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-mk-muted mb-1">Tugas &amp; Latihan/Persiapan 1 Minggu Kedepan</label>
                        <textarea name="homework_notes" rows="2" maxlength="2000"
                                  class="w-full border border-mk-borderLight rounded-xl px-3 py-2.5 text-sm bg-mk-card
                                         focus:outline-none focus:ring-2 focus:ring-mk-accent/30 resize-y"
                                  placeholder="Contoh: Latihan 15 menit per hari">{{ old('homework_notes', $teacherNote?->homework_notes) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-mk-muted mb-1">Catatan</label>
                        <textarea name="notes" rows="2" maxlength="2000"
                                  class="w-full border border-mk-borderLight rounded-xl px-3 py-2.5 text-sm bg-mk-card
                                         focus:outline-none focus:ring-2 focus:ring-mk-accent/30 resize-y"
                                  placeholder="Catatan tambahan untuk murid/orang tua">{{ old('notes', $teacherNote?->notes) }}</textarea>
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl font-semibold text-sm transition-colors appearance-none btn-mk-primary">
                        Simpan Catatan
                    </button>
                </form>
            </div>
        </details>
    </div>
@endif
