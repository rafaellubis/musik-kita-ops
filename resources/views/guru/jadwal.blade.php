<x-guru-layout title="Jadwal Saya">

@php
    $showSessionActionsMap = $sesi->mapWithKeys(fn ($s) => [
        $s->id => $tanggal === $today
            || in_array($s->status, ['HADIR', 'HADIR_TERLAMBAT'], true)
            || (
                (int) $s->substitute_teacher_id === (int) $teacher->id
                && $s->status === 'DIGANTI'
                && $s->honor_code !== null
            ),
    ]);
@endphp

<div x-data="guruJadwalCalendar(@js($calendarBootstrap))"
     x-cloak
     class="guru-jadwal-calendar">

    {{-- ===== Strip kalender horizontal (slide lintas bulan) ===== --}}
    <section class="px-4 lg:px-6 pt-2.5 pb-1">
        <div class="flex items-center justify-between mb-1.5">
            <button type="button"
                    @click="openMonthPicker()"
                    class="text-sm font-bold text-on-surface tracking-tight hover:text-secondary transition-colors text-left"
                    :aria-label="'Pilih bulan, ' + monthLabel"
                    x-text="monthLabel">
            </button>
        </div>

        <p id="guru-date-strip-help" class="sr-only">
            Gunakan tombol panah, roda mouse, drag dengan mouse, swipe touchscreen, atau tombol panah keyboard untuk berpindah tanggal.
        </p>

        <div class="flex items-center gap-2">
            <button type="button"
                    @click="scrollDateStrip(-1)"
                    :disabled="!canScrollLeft"
                    class="hidden lg:inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg
                           border border-outline-variant/30 bg-surface-container-lowest text-on-surface-variant
                           hover:bg-secondary/10 hover:text-secondary focus-visible:outline-none focus-visible:ring-2
                           focus-visible:ring-secondary focus-visible:ring-offset-2 disabled:cursor-not-allowed
                           disabled:opacity-35 disabled:hover:bg-surface-container-lowest transition-colors"
                    aria-label="Geser tanggal 7 hari ke belakang">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            <div x-ref="strip"
                 role="group"
                 aria-label="Pilihan tanggal jadwal"
                 aria-describedby="guru-date-strip-help"
                 class="guru-date-strip min-w-0 flex-1 overflow-x-auto pb-1.5 -mx-1 px-1"
                 @scroll.passive="onStripScroll()"
                 @wheel="onStripWheel($event)"
                 @click.capture="onStripClickCapture($event)"
                 @pointerdown="onStripPointerDown($event)"
                 @pointermove="onStripPointerMove($event)"
                 @pointerup="onStripPointerEnd($event)"
                 @pointercancel="onStripPointerEnd($event)"
                 @lostpointercapture="onStripPointerEnd($event)"
                 @keydown.arrow-left.prevent="moveDateSelection(-1)"
                 @keydown.arrow-right.prevent="moveDateSelection(1)">
                <div x-ref="stripInner" class="flex gap-1 min-w-min py-0.5">
                    <template x-for="d in dates" :key="d.date">
                        <button type="button"
                                :data-date="d.date"
                                @click="selectDate(d.date)"
                                :tabindex="d.date === selectedDate ? 0 : -1"
                                :aria-current="d.date === selectedDate ? 'date' : null"
                                :aria-label="d.day_name + ' ' + d.day_number"
                                class="guru-date-chip h-12 w-11 shrink-0 flex flex-col items-center justify-center rounded-md
                                       transition-all duration-200 active:scale-90 relative border"
                                :class="chipClasses(d)">
                            <span class="text-[8px] font-semibold tracking-wider uppercase"
                                  :class="d.date === selectedDate ? 'opacity-80' : 'text-on-surface-variant'"
                                  x-text="d.day_name"></span>
                            <span class="text-sm font-bold leading-tight"
                                  :class="d.date === selectedDate ? '' : 'text-on-surface'"
                                  x-text="d.day_number"></span>
                            <span x-show="hasSessionDot(d)"
                                  class="absolute bottom-0.5 w-1 h-1 rounded-full"
                                  :class="d.date === selectedDate ? 'bg-on-secondary/60' : 'bg-secondary'"></span>
                        </button>
                    </template>
                </div>
            </div>

            <button type="button"
                    @click="scrollDateStrip(1)"
                    :disabled="!canScrollRight"
                    class="hidden lg:inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg
                           border border-outline-variant/30 bg-surface-container-lowest text-on-surface-variant
                           hover:bg-secondary/10 hover:text-secondary focus-visible:outline-none focus-visible:ring-2
                           focus-visible:ring-secondary focus-visible:ring-offset-2 disabled:cursor-not-allowed
                           disabled:opacity-35 disabled:hover:bg-surface-container-lowest transition-colors"
                    aria-label="Geser tanggal 7 hari ke depan">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    </section>

    {{-- ===== Month picker dialog ===== --}}
    <dialog x-ref="monthDialog"
            class="guru-month-dialog rounded-2xl border border-outline-variant/20 bg-surface-container-lowest
                   text-on-surface p-4 w-[min(100%,20rem)] backdrop:bg-black/40"
            @click.self="closeMonthPicker()">
        <div class="flex items-center justify-between mb-4">
            <button type="button"
                    @click="shiftPickerYear(-1)"
                    class="p-2 rounded-full hover:bg-surface-container transition-colors"
                    aria-label="Tahun sebelumnya">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <span class="text-sm font-bold" x-text="monthPickerYear"></span>
            <button type="button"
                    @click="shiftPickerYear(1)"
                    class="p-2 rounded-full hover:bg-surface-container transition-colors"
                    aria-label="Tahun berikutnya">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        <div class="grid grid-cols-3 gap-2">
            <template x-for="(label, index) in monthNames" :key="index">
                <button type="button"
                        @click="pickMonth(index)"
                        class="py-2.5 rounded-lg text-sm font-semibold transition-colors min-h-[44px]"
                        :class="monthCellClasses(index)"
                        x-text="label">
                </button>
            </template>
        </div>

        <button type="button"
                @click="goToday()"
                class="mt-4 w-full py-2.5 rounded-xl text-sm font-bold bg-secondary text-on-secondary
                       hover:bg-secondary/90 transition-colors min-h-[44px]">
            Hari ini
        </button>
    </dialog>

    {{-- ===== Daftar sesi ===== --}}
    <section class="px-4 lg:px-6 pb-24 lg:pb-8">
        <div id="jadwal-sesi-list"
             class="guru-sesi-list transition-opacity duration-150"
             :class="{ 'opacity-50 pointer-events-none': loadingSessions }">
            @include('guru._jadwal-sesi-list', [
                'sesi' => $sesi,
                'tanggal' => $tanggal,
                'today' => $today,
                'teacher' => $teacher,
                'selectedDate' => $selectedDate,
                'showSessionActionsMap' => $showSessionActionsMap,
            ])
        </div>
    </section>
</div>

</x-guru-layout>
