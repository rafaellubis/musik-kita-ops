@php
    $isToday = $tanggal === $today;
    $showSessionActionsMap = $showSessionActionsMap ?? collect();
@endphp

{{-- Header jumlah sesi + badge LIVE --}}
<div class="flex items-center justify-between mb-4 px-1">
    <h2 class="text-sm text-on-surface-variant font-medium">
        {{ $sesi->count() }} Sesi
        @if($isToday)
            Hari Ini
        @else
            — {{ $selectedDate->locale('id')->isoFormat('dddd, D MMM') }}
        @endif
    </h2>
    @if($isToday && $sesi->where('status', 'SCHEDULED')->count() > 0)
        <span class="text-[11px] font-semibold bg-secondary-container text-on-secondary-container
                     px-2.5 py-1 rounded-full animate-pulse">
            LIVE
        </span>
    @endif
</div>

@if($sesi->isEmpty())
    <div class="bg-surface-container-lowest border border-outline-variant/20 rounded-2xl
                px-6 py-16 text-center fade-in-up">
        <div class="text-4xl mb-3 opacity-60">📅</div>
        <p class="text-on-surface-variant text-sm">
            @if($isToday)
                Tidak ada sesi pada hari ini.
            @else
                Tidak ada sesi pada tanggal ini.
            @endif
        </p>
        @unless($isToday)
            <button type="button"
                    data-action="go-today"
                    class="inline-block mt-4 text-sm font-semibold text-secondary hover:underline">
                ← Kembali ke hari ini
            </button>
        @endunless
    </div>
@else
    <div class="space-y-3">
        @foreach($sesi as $sIdx => $s)
            @php
                $showSessionActions = $showSessionActionsMap instanceof \Illuminate\Support\Collection
                    ? ($showSessionActionsMap[$s->id] ?? false)
                    : ($showSessionActionsMap[$s->id] ?? (
                        $tanggal === $today
                        || in_array($s->status, ['HADIR', 'HADIR_TERLAMBAT'], true)
                        || (
                            (int) $s->substitute_teacher_id === (int) $teacher->id
                            && $s->status === 'DIGANTI'
                            && $s->honor_code !== null
                        )
                    ));
            @endphp
            @include('guru._sesi-card', [
                'sesi' => $s,
                'teacher' => $teacher,
                'sIdx' => $sIdx,
                'showSessionActions' => $showSessionActions,
            ])
        @endforeach
    </div>
@endif
