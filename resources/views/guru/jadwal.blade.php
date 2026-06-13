<x-guru-layout title="Jadwal Saya">

@php
    $isToday    = $tanggal === $today;
    $dayNames   = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
@endphp

{{-- ===== Horizontal Weekly Calendar Strip ===== --}}
<section class="px-4 lg:px-6 pt-2.5 pb-1">
    {{-- Month header + chevron navigation --}}
    <div class="flex items-center justify-between mb-1.5">
        <h2 class="text-sm font-bold text-on-surface tracking-tight">
            {{ $selectedDate->locale('id')->isoFormat('MMMM YYYY') }}
        </h2>
        <div class="flex gap-0.5">
            <a href="{{ route('guru.jadwal', $prevWeekNav) }}"
               class="p-1 rounded-full hover:bg-surface-container transition-colors active:scale-90"
               title="Minggu sebelumnya">
                <svg class="w-3 h-3 text-on-surface" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <a href="{{ route('guru.jadwal', $nextWeekNav) }}"
               class="p-1 rounded-full hover:bg-surface-container transition-colors active:scale-90"
               title="Minggu berikutnya">
                <svg class="w-3 h-3 text-on-surface" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>

    {{-- Date chips row --}}
    <div class="grid grid-cols-7 gap-1 pb-1.5 px-1 max-w-md"
    >
        @foreach($weekDates as $idx => $wd)
            @php
                $wdStr      = $wd->toDateString();
                $isSelected = $wdStr === $tanggal;
                $isWdToday  = $wdStr === $today;
                $hasSesi    = ($sesiCountPerDay[$wdStr] ?? 0) > 0;
            @endphp
            <a href="{{ route('guru.jadwal', ['date' => $wdStr]) }}"
               class="w-full h-12 flex flex-col items-center justify-center rounded-md
                      transition-all duration-200 active:scale-90 relative
                      fade-in-up
                      @if($isSelected)
                          bg-secondary text-on-secondary shadow-md
                      @elseif($isWdToday)
                          bg-secondary-container text-on-secondary-container border border-secondary/30
                      @else
                          bg-surface-container-lowest border border-outline-variant/20
                          hover:bg-secondary/5
                      @endif
               "
               style="animation-delay: {{ ($idx + 1) * 60 }}ms"
            >
                <span class="text-[8px] font-semibold tracking-wider uppercase
                    {{ $isSelected ? 'opacity-80' : 'text-on-surface-variant' }}
                ">{{ $dayNames[$idx] }}</span>
                <span class="text-sm font-bold leading-tight
                    {{ $isSelected ? '' : 'text-on-surface' }}
                ">{{ $wd->day }}</span>

                {{-- Dot indicator for sessions --}}
                @if($hasSesi && !$isSelected)
                    <span class="absolute bottom-0.5 w-1 h-1 rounded-full bg-secondary"></span>
                @elseif($hasSesi && $isSelected)
                    <span class="absolute bottom-0.5 w-1 h-1 rounded-full bg-on-secondary/60"></span>
                @endif
            </a>
        @endforeach
    </div>
</section>

{{-- ===== Schedule List — Unified (responsive cards) ===== --}}
<section class="px-4 lg:px-6 pb-24 lg:pb-8">
    {{-- Session count + today badge --}}
    <div class="flex items-center justify-between mb-4 px-1">
        <h3 class="text-sm text-on-surface-variant font-medium">
            {{ $sesi->count() }} Sesi
            @if($isToday)
                Hari Ini
            @else
                — {{ $selectedDate->locale('id')->isoFormat('dddd, D MMM') }}
            @endif
        </h3>
        @if($isToday && $sesi->where('status', 'SCHEDULED')->count() > 0)
            <span class="text-[11px] font-semibold bg-secondary-container text-on-secondary-container
                         px-2.5 py-1 rounded-full animate-pulse">
                LIVE
            </span>
        @endif
    </div>

    @if($sesi->isEmpty())
        {{-- Empty state --}}
        <div class="bg-surface-container-lowest border border-outline-variant/20 rounded-2xl
                    px-6 py-16 text-center fade-in-up">
            <div class="text-4xl mb-3 opacity-60">📅</div>
            <p class="text-on-surface-variant text-sm">Tidak ada sesi pada hari ini.</p>
            @unless($isToday)
                <a href="{{ route('guru.jadwal') }}"
                   class="inline-block mt-4 text-sm font-semibold text-secondary hover:underline">
                    ← Kembali ke hari ini
                </a>
            @endunless
        </div>
    @else
        <div class="space-y-3">
            @foreach($sesi as $sIdx => $s)
                @php
                    $isCompleted  = in_array($s->status, ['HADIR', 'HADIR_TERLAMBAT'], true);
                    $isDiganti    = $s->status === 'DIGANTI';
                    $isLibur      = $s->status === 'LIBUR';
                    $isCancelled  = $s->status === 'CANCELLED';
                    $isScheduled  = $s->status === 'SCHEDULED';
                    $isMuted      = $isCompleted || $isCancelled;
                    $packageLabel = $s->enrollment?->package?->code ?? '';
                    $instrumentName = $s->enrollment?->package?->instrument?->name ?? '';
                    $instrumentIcon = match(strtolower($instrumentName)) {
                        'piano'       => '🎹',
                        'gitar', 'guitar' => '🎸',
                        'biola', 'violin' => '🎻',
                        'drum', 'drums'   => '🥁',
                        'vokal', 'vocal'  => '🎤',
                        default       => '🎵',
                    };

                    $showSessionActions = $tanggal === $today
                        || in_array($s->status, ['HADIR', 'HADIR_TERLAMBAT'], true)
                        || (
                            (int) $s->substitute_teacher_id === (int) $teacher->id
                            && $s->status === 'DIGANTI'
                            && $s->honor_code !== null
                        );
                @endphp

                {{-- Session Card --}}
                <div class="bg-surface-container-lowest border border-outline-variant/20 rounded-2xl
                            shadow-[rgba(74,14,14,0.04)_0px_2px_8px]
                            hover:shadow-[rgba(74,14,14,0.08)_0px_4px_12px]
                            transition-all duration-300
                            {{ $isMuted ? 'opacity-70' : '' }}
                            fade-in-up"
                     style="animation-delay: {{ ($sIdx + 2) * 80 }}ms"
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
                                {{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}
                            </span>
                            <div class="w-5 h-px my-0.5
                                {{ $isCompleted ? 'bg-outline-variant/30' : ($isLibur ? 'bg-purple-200' : 'bg-primary/10') }}
                            "></div>
                            <span class="text-[10px] opacity-70">
                                {{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}
                            </span>
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-primary text-base leading-snug truncate">
                                {{ $s->student->full_name }}
                            </h4>
                            <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                <span class="text-sm">{{ $instrumentIcon }}</span>
                                @if($packageLabel)
                                    <span class="text-[10px] bg-secondary-container/40 text-secondary font-mono px-1.5 py-0.5 rounded font-medium">
                                        {{ $packageLabel }}
                                    </span>
                                @endif
                            </div>
                            @include('guru._sesi-identitas', ['sesi' => $s])
                            @if($s->room)
                                <div class="text-xs text-on-surface-variant/60 mt-0.5">📍 {{ $s->room->name }}</div>
                            @endif
                            @if($s->substitute_teacher_id === auth()->user()->teacher?->id)
                                <div class="text-[10px] text-blue-600 font-medium mt-0.5">Anda sebagai pengganti</div>
                            @endif
                        </div>

                        {{-- Status badge --}}
                        <div class="flex flex-col items-end gap-2 shrink-0">
                            @php
                                $statusMap = [
                                    'HADIR'           => ['bg-secondary-container text-on-secondary-container', 'Hadir', '✓'],
                                    'HADIR_TERLAMBAT' => ['bg-yellow-100 text-yellow-700', 'Terlambat', '⏱'],
                                    'SCHEDULED'       => ['bg-surface-container-high text-on-surface-variant', 'Terjadwal', ''],
                                    'LIBUR'           => ['bg-purple-100 text-purple-700', 'Libur', ''],
                                    'HANGUS'          => ['bg-error-container text-on-error-container', 'Hangus', ''],
                                    'IZIN_RESCHEDULE' => ['bg-orange-100 text-orange-600', 'Izin', ''],
                                    'IZIN_VIDEO'      => ['bg-orange-100 text-orange-600', 'Izin Video', ''],
                                    'DIGANTI'         => ['bg-blue-100 text-blue-600', 'Diganti', ''],
                                    'CANCELLED'       => ['bg-surface-container-high text-on-surface-variant', 'Batal', ''],
                                ];
                                [$badgeCls, $badgeLabel, $badgeIcon] = $statusMap[$s->status] ?? ['bg-surface-container-high text-on-surface-variant', $s->status, ''];
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold {{ $badgeCls }}">
                                {{ $badgeLabel }}
                            </span>
                            @if($isCompleted)
                                <svg class="w-5 h-5 text-secondary" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                            @endif
                        </div>
                    </div>

                    {{-- Expandable action section --}}
                    @if($showSessionActions)
                        <div class="border-t border-outline-variant/15">
                            @include('guru._sesi-absensi-actions', ['sesi' => $s, 'teacher' => $teacher])
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>

</x-guru-layout>
