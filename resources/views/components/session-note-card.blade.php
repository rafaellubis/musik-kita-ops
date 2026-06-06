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

<div {{ $attributes->merge(['class' => 'mb-4 border border-[#E8D5A0] rounded-xl p-4 bg-[#FBF3E0]/30 last:mb-0']) }}>
    <table class="w-full text-sm mb-4">
        <tr>
            <td class="text-mk-muted pr-2 py-0.5 align-top whitespace-nowrap w-36">Nama</td>
            <td class="py-0.5">: {{ $studentName }}</td>
        </tr>
        <tr>
            <td class="text-mk-muted pr-2 py-0.5 align-top whitespace-nowrap">
                {{ filled($substituteTeacherName) ? 'Guru Pengganti' : 'Guru Pengajar' }}
            </td>
            <td class="py-0.5">: {{ filled($substituteTeacherName) ? $substituteTeacherName : $teacherName }}</td>
        </tr>
        <tr>
            <td class="text-mk-muted pr-2 py-0.5 align-top whitespace-nowrap">Tanggal Sesi</td>
            <td class="py-0.5">: {{ $sessionDate }}</td>
        </tr>
        <tr>
            <td class="text-mk-muted pr-2 py-0.5 align-top whitespace-nowrap">Rating Anak hari Ini</td>
            <td class="py-0.5">
                : @if($sessionRating)
                    <span class="text-yellow-500 tracking-wide">
                        @for ($i = 1; $i <= 5; $i++)
                            {{ $i <= $sessionRating ? '★' : '☆' }}
                        @endfor
                    </span>
                @else
                    <span class="text-gray-400">—</span>
                @endif
            </td>
        </tr>
    </table>

    @if($showEmptyBadge && $isEmpty)
        <div class="mb-3">
            <span class="text-[10px] bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-medium">Belum diisi</span>
        </div>
    @endif

    <div class="space-y-3 text-sm">
        <div>
            <div class="font-medium text-mk-text mb-1">Materi yang dipelajari :</div>
            <div class="border border-[#E8D5A0] rounded-lg bg-white px-3 py-2.5 min-h-[3rem] whitespace-pre-line text-mk-text">
                {{ filled($materialLearned) ? $materialLearned : '—' }}
            </div>
        </div>
        <div>
            <div class="font-medium text-mk-text mb-1">Tugas Latihan/Persiapan Selama 1 minggu kedepan :</div>
            <div class="border border-[#E8D5A0] rounded-lg bg-white px-3 py-2.5 min-h-[3rem] whitespace-pre-line text-mk-text">
                {{ filled($homeworkNotes) ? $homeworkNotes : '—' }}
            </div>
        </div>
        <div>
            <div class="font-medium text-mk-text mb-1">Catatan</div>
            <div class="border border-[#E8D5A0] rounded-lg bg-white px-3 py-2.5 min-h-[3rem] whitespace-pre-line text-mk-text">
                {{ filled($notes) ? $notes : '—' }}
            </div>
        </div>
    </div>
</div>
