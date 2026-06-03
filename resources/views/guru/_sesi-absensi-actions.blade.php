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
                    ✓ Saya Hadir Menggantikan
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
                    class="flex-1 py-2.5 rounded-xl border-2 border-yellow-400 text-yellow-600
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
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
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
