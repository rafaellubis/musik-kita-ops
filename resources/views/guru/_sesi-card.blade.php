@php
    $isCompleted  = in_array($sesi->status, ['HADIR', 'HADIR_TERLAMBAT'], true);
    $isDiganti    = $sesi->status === 'DIGANTI';
    $isLibur      = $sesi->status === 'LIBUR';
    $isCancelled  = $sesi->status === 'CANCELLED';
    $isScheduled  = $sesi->status === 'SCHEDULED';
    $isMuted      = $isCompleted || $isCancelled;

    $packageLabel = $sesi->enrollment?->package?->code ?? '';
    $instrumentName = $sesi->enrollment?->package?->instrument?->name ?? '';
    $instrumentIcon = match(strtolower($instrumentName)) {
        'piano'           => '🎹',
        'gitar', 'guitar' => '🎸',
        'biola', 'violin' => '🎻',
        'drum', 'drums'   => '🥁',
        'vokal', 'vocal'  => '🎤',
        default           => '🎵',
    };

    // Calculate showSessionActions if not passed
    if (!isset($showSessionActions)) {
        $sessionDateStr = \Carbon\Carbon::parse($sesi->session_date)->toDateString();
        $todayStr = today()->toDateString();
        $showSessionActions = $sessionDateStr === $todayStr
            || in_array($sesi->status, ['HADIR', 'HADIR_TERLAMBAT'], true)
            || (
                (int) $sesi->substitute_teacher_id === (int) $teacher->id
                && $sesi->status === 'DIGANTI'
                && $sesi->honor_code !== null
            );
    }

    $isSubstitutePending = (int) $sesi->substitute_teacher_id === (int) $teacher->id
        && $sesi->status === 'DIGANTI'
        && $sesi->honor_code === null;
    $isOwnScheduled = $sesi->status === 'SCHEDULED'
        && (int) $sesi->teacher_id === (int) $teacher->id
        && !$sesi->substitute_teacher_id;
    $isSubstituteConfirmed = (int) $sesi->substitute_teacher_id === (int) $teacher->id
        && $sesi->status === 'DIGANTI'
        && $sesi->honor_code !== null;
    $canWriteNotes = (
        in_array($sesi->status, ['HADIR', 'HADIR_TERLAMBAT'], true)
        && (int) $sesi->teacher_id === (int) $teacher->id
    ) || $isSubstituteConfirmed;
@endphp

{{-- Session Card --}}
<div class="bg-surface-container-lowest border border-outline-variant/20 rounded-2xl
            shadow-sm
            hover:shadow
            transition-all duration-300
            {{ $isMuted ? 'opacity-70' : '' }}
            fade-in-up"
     @if(isset($sIdx)) style="animation-delay: {{ ($sIdx + 2) * 80 }}ms" @endif
>
    {{-- Card main row --}}
    <div class="flex items-center gap-4 p-4">
        {{-- Time block --}}
        <div class="w-14 h-14 rounded-xl flex flex-col items-center justify-center shrink-0 border
            {{ $isCompleted
                ? 'bg-surface-container border-outline-variant/20 text-on-surface-variant'
                : ($isLibur
                    ? 'bg-purple-50 border-purple-200 text-purple-600'
                    : 'bg-primary/5 border-primary/10 text-primary')
            }}
        ">
            <span class="text-xs font-bold leading-tight">
                {{ \Carbon\Carbon::parse($sesi->start_time)->format('H:i') }}
            </span>
            <div class="w-5 h-px my-0.5
                {{ $isCompleted ? 'bg-outline-variant/30' : ($isLibur ? 'bg-purple-200' : 'bg-primary/10') }}
            "></div>
            <span class="text-[10px] opacity-70">
                {{ \Carbon\Carbon::parse($sesi->end_time)->format('H:i') }}
            </span>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-primary text-base leading-snug truncate">
                {{ $sesi->student->full_name }}
            </h4>
            <div class="flex flex-wrap items-center gap-1.5 mt-1">
                <span class="text-sm">{{ $instrumentIcon }}</span>
                @if($packageLabel)
                    <span class="text-[10px] bg-secondary-container/40 text-secondary font-mono px-1.5 py-0.5 rounded font-medium">
                        {{ $packageLabel }}
                    </span>
                @endif
            </div>
            @include('guru._sesi-identitas', ['sesi' => $sesi])
            @if($sesi->room)
                <div class="text-xs text-on-surface-variant/60 mt-0.5">📍 {{ $sesi->room->name }}</div>
            @endif
            @if($sesi->substitute_teacher_id === $teacher->id)
                <div class="text-[10px] text-blue-600 font-medium mt-0.5">Anda sebagai pengganti</div>
            @endif
            @if($sesi->substitute_teacher_id && $sesi->substitute_teacher_id !== $teacher->id)
                <div class="text-[10px] text-amber-600 font-medium mt-0.5">
                    Digantikan oleh: {{ $sesi->substituteTeacher?->name ?? '?' }}
                    @if($sesi->honor_code === null)
                        <span class="text-outline/60 font-normal">(menunggu konfirmasi)</span>
                    @else
                        <span class="text-green-600 font-normal">✓</span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Status badge + Hadir Button / Completed Icon --}}
        <div class="flex flex-col items-end gap-2.5 shrink-0"
             @if($showSessionActions && ($isOwnScheduled || $isSubstitutePending)) :aria-busy="loading" @endif
             @if($showSessionActions && ($isOwnScheduled || $isSubstitutePending))
                 @php
                     $sessionStart = \Carbon\Carbon::parse($sesi->session_date)
                         ->setTimeFromTimeString($sesi->start_time)
                         ->subMinutes(15);
                     $sessionStartEpoch = $sessionStart->getTimestamp() * 1000;
                 @endphp
                  x-data="guruSessionActivation({
                      startEpoch: {{ $sessionStartEpoch }},
                      timeLabel: 'Absensi aktif pkl {{ \Carbon\Carbon::parse($sesi->start_time)->subMinutes(15)->format('H:i') }}'
                  })"
             @endif
        >
            @include('guru._badge-status', ['status' => $sesi->status])
            @if($showSessionActions && ($isOwnScheduled || $isSubstitutePending))
                @if($isOwnScheduled)
                    <form method="POST" action="{{ route('guru.absensi.update', $sesi) }}" class="m-0 flex flex-col items-end"
                          @submit.prevent="
                              // ponytail: handle submit via Fetch API secara non-blocking
                               loading = true; feedback = ''; errorFeedback = '';
                               $el.querySelectorAll('button').forEach(b => b.disabled = true);
                               fetch($el.action, {
                                  method: 'POST',
                                  headers: {
                                      'Content-Type': 'application/json',
                                      'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                      'Accept': 'application/json',
                                      'X-Requested-With': 'XMLHttpRequest'
                                  },
                                  body: JSON.stringify({
                                      _method: 'PATCH',
                                      status: 'HADIR'
                                  })
                              })
                              .then(res => res.json())
                              .then(data => {
                                   if (data.success) {
                                        loading = false;
                                        feedback = 'Kehadiran berhasil disimpan.';
                                        window.dispatchEvent(new CustomEvent('session-updated', { detail: { date: '{{ $sesi->session_date }}' } }));
                                  } else {
                                       errorFeedback = data.message || 'Terjadi kesalahan';
                                       loading = false;
                                      $el.querySelectorAll('button').forEach(b => b.disabled = false);
                                  }
                              })
                              .catch(err => {
                                   errorFeedback = 'Gagal mencatat absensi.';
                                   loading = false;
                                  $el.querySelectorAll('button').forEach(b => b.disabled = false);
                              });
                          ">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="HADIR">
                        <button type="submit"
                                :disabled="isDisabled"
                                class="w-14 h-14 rounded-2xl font-bold bg-secondary text-on-secondary hover:bg-secondary/90 transition-all shadow-sm active:scale-95 disabled:opacity-50 disabled:pointer-events-none disabled:shadow-none flex flex-col items-center justify-center gap-0.5"
                                title="Absen Hadir">
                            <span class="text-base leading-none">✓</span>
                            <span class="text-[10px] uppercase tracking-wider leading-none">Hadir</span>
                        </button>
                        <span x-show="isDisabled" class="text-[9px] text-amber-700 leading-none mt-1 whitespace-nowrap bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded font-medium" x-text="timeLabel" style="display: none;"></span>
                    </form>
                @elseif($isSubstitutePending)
                    <form method="POST" action="{{ route('guru.absensi.confirm-substitute', $sesi) }}" class="m-0 flex flex-col items-end"
                          @submit.prevent="
                              // ponytail: konfirmasi kehadiran pengganti secara asynchronous
                               loading = true; feedback = ''; errorFeedback = '';
                               $el.querySelectorAll('button').forEach(b => b.disabled = true);
                               fetch($el.action, {
                                  method: 'POST',
                                  headers: {
                                      'Content-Type': 'application/json',
                                      'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                      'Accept': 'application/json',
                                      'X-Requested-With': 'XMLHttpRequest'
                                  },
                                  body: JSON.stringify({
                                      action: 'hadir'
                                  })
                              })
                              .then(res => res.json())
                              .then(data => {
                                   if (data.success) {
                                        loading = false;
                                        feedback = 'Kehadiran berhasil disimpan.';
                                        window.dispatchEvent(new CustomEvent('session-updated', { detail: { date: '{{ $sesi->session_date }}' } }));
                                  } else {
                                       errorFeedback = data.message || 'Terjadi kesalahan';
                                       loading = false;
                                      $el.querySelectorAll('button').forEach(b => b.disabled = false);
                                  }
                              })
                              .catch(err => {
                                   errorFeedback = 'Gagal melakukan konfirmasi.';
                                   loading = false;
                                  $el.querySelectorAll('button').forEach(b => b.disabled = false);
                              });
                          ">
                        @csrf
                        <input type="hidden" name="action" value="hadir">
                        <button type="submit"
                                :disabled="isDisabled"
                                class="w-14 h-14 rounded-2xl font-bold bg-secondary text-on-secondary hover:bg-secondary/90 transition-all shadow-sm active:scale-95 disabled:opacity-50 disabled:pointer-events-none disabled:shadow-none flex flex-col items-center justify-center gap-0.5"
                                title="Konfirmasi Hadir (Pengganti)">
                            <span class="text-base leading-none">✓</span>
                            <span class="text-[10px] uppercase tracking-wider leading-none">Hadir</span>
                        </button>
                        <span x-show="isDisabled" class="text-[9px] text-amber-700 leading-none mt-1 whitespace-nowrap bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded font-medium" x-text="timeLabel" style="display: none;"></span>
                    </form>
                @endif
                <span x-show="feedback" role="status" class="sr-only" x-text="feedback"></span>
                <span x-show="errorFeedback" role="alert" class="sr-only" x-text="errorFeedback"></span>
            @elseif($isCompleted)
                <div class="w-8 h-8 rounded-full bg-secondary/10 text-secondary flex items-center justify-center border border-secondary/20">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
            @endif
        </div>
    </div>

    {{-- Expandable action section --}}
    @if($canWriteNotes)
        <div class="border-t border-outline-variant/15">
            @include('guru._sesi-absensi-actions', ['sesi' => $sesi, 'teacher' => $teacher])
        </div>
    @endif
</div>
