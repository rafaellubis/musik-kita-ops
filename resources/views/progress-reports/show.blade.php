<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Laporan: {{ $progressReport->student->full_name }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $progressReport->teacher->name }} · {{ $progressReport->enrollment->package->code }} · {{ $progressReport->namaBulan() }}
                </div>
            </div>
            @if($progressReport->status === 'SUBMITTED')
                <a href="{{ route('progress-reports.pdf', $progressReport) }}" class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">
                    ↓ Download PDF
                </a>
            @endif
        </div>
    </x-slot>

    @php
        $avgRating = $progressReport->averageSessionRating();
        $headerStars = $avgRating !== null
            ? \App\Models\ProgressReport::renderStars((int) round($avgRating))
            : '—';
        $weekly = $progressReport->weeklyMaterials();
        $pkg = $progressReport->enrollment->package;
        $emoji = $progressReport->instrumentEmoji();
        $pct = $progressReport->progress_percent ?? 0;
    @endphp

    <div class="py-6 px-4 lg:px-8 max-w-3xl space-y-4">

        {{-- Header meta --}}
        <div class="bg-white shadow-sm rounded-lg p-5">
            <table class="w-full text-sm">
                <tr><td class="text-gray-500 w-40 py-0.5">Nama</td><td class="py-0.5 font-semibold">{{ $progressReport->student->full_name }}</td></tr>
                <tr><td class="text-gray-500 py-0.5">Instrumen</td><td class="py-0.5">{{ $pkg->instrument->name }}</td></tr>
                <tr><td class="text-gray-500 py-0.5">Guru Pengajar</td><td class="py-0.5">{{ $progressReport->teacher->name }}</td></tr>
                <tr><td class="text-gray-500 py-0.5">Bulan</td><td class="py-0.5">{{ $progressReport->namaBulan() }}</td></tr>
                <tr>
                    <td class="text-gray-500 py-0.5">Rating Anak</td>
                    <td class="py-0.5 text-yellow-500 tracking-wide">{{ $headerStars }}</td>
                </tr>
            </table>
        </div>

        {{-- Kehadiran & Materi Minggu 1–4 --}}
        <div class="bg-white shadow-sm rounded-lg p-5">
            <div class="font-semibold text-sm text-gray-700 mb-3">Kehadiran dan Materi yang Dipelajari</div>
            <div class="space-y-2">
                @foreach ([1 => 'Minggu 1', 2 => 'Minggu 2', 3 => 'Minggu 3', 4 => 'Minggu 4'] as $seq => $label)
                    <div class="flex items-start gap-3">
                        <span class="text-sm text-gray-500 w-20 shrink-0 pt-2">{{ $label }}</span>
                        <div class="flex-1 border border-gray-200 rounded-lg bg-gray-50 px-3 py-2 text-sm min-h-[2.5rem] whitespace-pre-line text-gray-700">
                            {{ $weekly[$seq] ?? '—' }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Rating bulanan --}}
        <div class="bg-white shadow-sm rounded-lg p-5">
            <div class="font-semibold text-sm text-gray-700 mb-3">
                Perkembangan {{ $progressReport->student->full_name }} Selama Les di Bulan {{ $progressReport->namaBulan() }}
            </div>
            <div class="space-y-2 text-sm">
                @foreach ([
                    'Teknik Bermain' => $progressReport->rating_teknik,
                    'Materi'         => $progressReport->rating_materi,
                    'Reading'        => $progressReport->rating_reading,
                    'Repertoar'      => $progressReport->rating_repertoar,
                ] as $label => $rating)
                    <div class="flex items-center gap-3">
                        <span class="text-gray-500 w-32">{{ $label }}</span>
                        <span class="text-yellow-500 tracking-wide">
                            {{ $rating ? \App\Models\ProgressReport::renderStars($rating) : '—' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Catatan naratif --}}
        @if($progressReport->catatan_perkembangan_musikal)
            <div class="bg-white shadow-sm rounded-lg p-5">
                <div class="font-semibold text-sm text-gray-700 mb-2">Catatan Perkembangan Musikal</div>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $progressReport->catatan_perkembangan_musikal }}</p>
            </div>
        @endif

        @if($progressReport->catatan_karakter)
            <div class="bg-white shadow-sm rounded-lg p-5">
                <div class="font-semibold text-sm text-gray-700 mb-2">Catatan Karakter</div>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $progressReport->catatan_karakter }}</p>
            </div>
        @endif

        {{-- Kesimpulan Progress --}}
        @if($progressReport->kesimpulan_progress)
            <div class="bg-white shadow-sm rounded-lg p-5">
                <div class="font-semibold text-sm text-gray-700 mb-3">Kesimpulan Progress</div>
                <div class="grid grid-cols-4 gap-2 text-xs text-center">
                    @foreach (\App\Models\ProgressReport::kesimpulanLabels() as $key => $label)
                        <div class="border rounded-lg px-2 py-3
                            {{ $progressReport->kesimpulan_progress === $key
                                ? 'border-amber-600 bg-amber-50 font-bold text-amber-800'
                                : 'border-gray-200 text-gray-400' }}">
                            {{ $label }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Footer — Level + Progress bar --}}
        <div class="bg-white shadow-sm rounded-lg p-5">
            <div class="text-sm text-gray-700 mb-3">
                {{ $emoji }} {{ $pkg->instrument->name }} · {{ $pkg->getLevelLabel() }}
            </div>
            <div class="w-full bg-gray-100 rounded-full h-4 overflow-hidden border border-gray-200">
                <div class="bg-amber-400 h-4 rounded-full flex items-center justify-end pr-2"
                     style="width: {{ $pct }}%; min-width: {{ $pct > 0 ? '2rem' : '0' }};">
                    @if($pct > 0)
                        <span class="text-[10px] text-white font-bold">{{ $pct }}%</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Catatan per sesi --}}
        @if($progressReport->sessionNotes->isNotEmpty())
            <div class="bg-white shadow-sm rounded-lg p-5">
                <div class="font-semibold text-sm text-gray-700 mb-3">Catatan Per Sesi</div>
                @foreach($progressReport->sessionNotes->sortBy([['session_date', 'asc'], ['sort_order', 'asc']]) as $note)
                    <x-session-note-card
                        class="mb-4 border-gray-200 bg-gray-50/50"
                        :student-name="$progressReport->student->full_name"
                        :teacher-name="$progressReport->teacher->name"
                        :substitute-teacher-name="$note->substitute_teacher_name"
                        :session-date="\Carbon\Carbon::parse($note->session_date)->locale('id')->isoFormat('D MMMM Y')"
                        :session-rating="$note->session_rating"
                        :material-learned="$note->material_learned"
                        :homework-notes="$note->homework_notes"
                        :notes="$note->notes"
                    />
                @endforeach
            </div>
        @endif

        <a href="{{ route('progress-reports.index') }}" class="text-sm text-gray-500 hover:underline">← Kembali</a>
    </div>
</x-app-layout>
