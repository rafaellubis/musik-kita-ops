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
                    $showSessionActions = $tanggal === $today
                        || in_array($s->status, ['HADIR', 'HADIR_TERLAMBAT'], true)
                        || (
                            (int) $s->substitute_teacher_id === (int) $teacher->id
                            && $s->status === 'DIGANTI'
                            && $s->honor_code !== null
                        );
                @endphp
                @include('guru._sesi-card', [
                    'sesi' => $s,
                    'teacher' => $teacher,
                    'sIdx' => $sIdx,
                    'showSessionActions' => $showSessionActions
                ])
            @endforeach
        </div>
    @endif
</section>

</x-guru-layout>
