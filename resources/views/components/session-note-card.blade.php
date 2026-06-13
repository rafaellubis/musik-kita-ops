@props([
    'studentName',
    'teacherName',
    'substituteTeacherName' => null,
    'sessionDate',
    'sessionRating' => null,
    'materialLearned' => null,
    'homeworkNotes' => null,
    'notes' => null,
    'showEmptyBadge' => false,
])

@php
    $isEmpty = blank($materialLearned) && blank($homeworkNotes) && blank($notes);
@endphp

<div {{ $attributes->merge(['class' => 'mb-4 border border-outline-variant/30 rounded-xl p-4 bg-surface-container-low shadow-sm last:mb-0']) }}>
    <div class="mb-5 space-y-3">
        <div class="flex flex-col md:flex-row md:items-start gap-1 md:gap-4">
            <span class="text-outline text-sm md:w-36 shrink-0">Nama</span>
            <span class="text-sm text-on-surface md:flex-1 font-semibold"><span class="hidden md:inline mr-1">:</span>{{ $studentName }}</span>
        </div>
        <div class="flex flex-col md:flex-row md:items-start gap-1 md:gap-4">
            <span class="text-outline text-sm md:w-36 shrink-0">{{ filled($substituteTeacherName) ? 'Guru Pengganti' : 'Guru Pengajar' }}</span>
            <span class="text-sm text-on-surface md:flex-1"><span class="hidden md:inline mr-1">:</span>{{ filled($substituteTeacherName) ? $substituteTeacherName : $teacherName }}</span>
        </div>
        <div class="flex flex-col md:flex-row md:items-start gap-1 md:gap-4">
            <span class="text-outline text-sm md:w-36 shrink-0">Tanggal Sesi</span>
            <span class="text-sm text-on-surface md:flex-1"><span class="hidden md:inline mr-1">:</span>{{ $sessionDate }}</span>
        </div>
        <div class="flex flex-col md:flex-row md:items-start gap-1 md:gap-4">
            <span class="text-outline text-sm md:w-36 shrink-0">Rating Anak hari Ini</span>
            <div class="text-sm text-on-surface md:flex-1">
                <span class="hidden md:inline mr-1">:</span>
                @if($sessionRating)
                    <span class="text-secondary tracking-wide">
                        @for ($i = 1; $i <= 5; $i++)
                            {{ $i <= $sessionRating ? '★' : '☆' }}
                        @endfor
                    </span>
                @else
                    <span class="text-outline">—</span>
                @endif
            </div>
        </div>
    </div>

    @if($showEmptyBadge && $isEmpty)
        <div class="mb-3">
            <span class="text-[10px] bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-medium">Belum diisi</span>
        </div>
    @endif

    <div class="space-y-4 text-sm">
        <div>
            <div class="font-semibold text-on-surface mb-1.5">Materi yang dipelajari :</div>
            <div class="border border-outline-variant/40 rounded-lg bg-background px-3 py-2.5 min-h-[3rem] whitespace-pre-line text-on-surface shadow-sm">
                {{ filled($materialLearned) ? $materialLearned : '—' }}
            </div>
        </div>
        <div>
            <div class="font-semibold text-on-surface mb-1.5">Tugas Latihan/Persiapan Selama 1 minggu kedepan :</div>
            <div class="border border-outline-variant/40 rounded-lg bg-background px-3 py-2.5 min-h-[3rem] whitespace-pre-line text-on-surface shadow-sm">
                {{ filled($homeworkNotes) ? $homeworkNotes : '—' }}
            </div>
        </div>
        <div>
            <div class="font-semibold text-on-surface mb-1.5">Catatan</div>
            <div class="border border-outline-variant/40 rounded-lg bg-background px-3 py-2.5 min-h-[3rem] whitespace-pre-line text-on-surface shadow-sm">
                {{ filled($notes) ? $notes : '—' }}
            </div>
        </div>
    </div>
</div>
