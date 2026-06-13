<x-guru-layout title="Isi Laporan">

@php
    $avgRating = $progressReport->averageSessionRating();
    $headerStars = $avgRating !== null
        ? \App\Models\ProgressReport::renderStars((int) round($avgRating))
        : '—';
    $weekly = $progressReport->weeklyMaterials();
    $mingguLabels = [1 => 'Minggu 1', 2 => 'Minggu 2', 3 => 'Minggu 3', 4 => 'Minggu 4'];
@endphp

<div class="px-4 pt-5 pb-2">
    <h1 class="text-base font-semibold text-on-background">{{ $progressReport->student->full_name }}</h1>
    <p class="text-sm text-outline">{{ $progressReport->enrollment->package->code }} · {{ $progressReport->namaBulan() }}</p>
</div>

<form method="POST" action="{{ route('guru.laporan.update', $progressReport) }}" onsubmit="return confirmSubmit(event)">
    @csrf @method('PUT')

    {{-- Header info + Rating Anak --}}
    <div class="mx-4 mb-4 bg-white border border-outline-variant/30 rounded-2xl p-4 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center gap-1 md:gap-4 mb-2">
            <span class="text-outline text-sm md:w-36">Nama</span>
            <span class="text-sm text-on-surface font-semibold md:flex-1"><span class="hidden md:inline mr-1">:</span>{{ $progressReport->student->full_name }}</span>
        </div>
        <div class="flex flex-col md:flex-row md:items-center gap-1 md:gap-4 mb-2">
            <span class="text-outline text-sm md:w-36">Instrumen</span>
            <span class="text-sm text-on-surface md:flex-1"><span class="hidden md:inline mr-1">:</span>{{ $progressReport->enrollment->package->instrument->name }}</span>
        </div>
        <div class="flex flex-col md:flex-row md:items-center gap-1 md:gap-4 mb-2">
            <span class="text-outline text-sm md:w-36">Guru Pengajar</span>
            <span class="text-sm text-on-surface md:flex-1"><span class="hidden md:inline mr-1">:</span>{{ $progressReport->teacher->name }}</span>
        </div>
        <div class="flex flex-col md:flex-row md:items-center gap-1 md:gap-4 mb-2">
            <span class="text-outline text-sm md:w-36">Bulan</span>
            <span class="text-sm text-on-surface md:flex-1"><span class="hidden md:inline mr-1">:</span>{{ $progressReport->namaBulan() }}</span>
        </div>
        <div class="flex flex-col md:flex-row md:items-center gap-1 md:gap-4">
            <span class="text-outline text-sm md:w-36">Rating Anak</span>
            <span class="text-sm text-on-surface md:flex-1"><span class="hidden md:inline mr-1">:</span><span class="text-secondary tracking-wide">{{ $headerStars }}</span></span>
        </div>
    </div>

    {{-- Kehadiran & Materi Minggu 1–4 --}}
    <div class="mx-4 mb-4 bg-white border border-outline-variant/30 rounded-2xl p-4 shadow-sm">
        <div class="font-semibold text-sm text-on-surface mb-4">Kehadiran dan Materi yang Dipelajari</div>
        <div class="space-y-4">
            @foreach ($mingguLabels as $seq => $label)
                <div class="flex flex-col md:flex-row md:items-start gap-1 md:gap-3">
                    <span class="text-sm text-outline md:w-24 shrink-0 md:pt-2">{{ $label }}</span>
                    <div class="flex-1 border border-outline-variant/40 rounded-lg bg-background px-3 py-2 text-sm min-h-[2.5rem] text-on-surface whitespace-pre-line shadow-sm">
                        {{ $weekly[$seq] ?? '—' }}
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-outline mt-4">Diisi otomatis dari catatan sesi. Edit via Dashboard → sesi terkait.</p>
    </div>

    {{-- Perkembangan bulanan — 4 star ratings --}}
    <div class="mx-4 mb-4 bg-white border border-outline-variant/30 rounded-2xl p-4 shadow-sm">
        <div class="font-semibold text-sm text-on-surface mb-5">
            Perkembangan {{ $progressReport->student->full_name }} Selama Les di Bulan {{ $progressReport->namaBulan() }}
        </div>
        <div class="space-y-6">
            @foreach (\App\Models\ProgressReport::monthlyRatingFields() as $field)
                <div class="flex flex-col md:flex-row md:items-start gap-2 md:gap-3">
                    <span class="text-sm font-semibold text-on-surface md:w-36 shrink-0 md:pt-2">{{ $field['label'] }}</span>
                    <div class="md:w-36 shrink-0">
                        <select name="{{ $field['rating'] }}"
                                class="w-full bg-background border border-outline-variant/50 rounded-lg px-2 py-2 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary transition-colors shadow-sm">
                            <option value="">—</option>
                            @for ($i = 1; $i <= 5; $i++)
                                <option value="{{ $i }}" @selected((int) old($field['rating'], $progressReport->{$field['rating']}) === $i)>
                                    {{ str_repeat('★', $i) }}{{ str_repeat('☆', 5 - $i) }}
                                </option>
                            @endfor
                        </select>
                        @error($field['rating'])<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex-1 min-w-0">
                        <textarea name="{{ $field['catatan'] }}" rows="2"
                                  placeholder="Catatan (opsional)..."
                                  class="w-full bg-background border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary transition-colors shadow-sm">{{ old($field['catatan'], $progressReport->{$field['catatan']}) }}</textarea>
                        @error($field['catatan'])<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Catatan naratif --}}
    <div class="mx-4 mb-4 bg-white border border-outline-variant/30 rounded-2xl p-4 shadow-sm">
        <div class="font-semibold text-sm text-on-surface mb-3">Catatan Guru Terhadap Perkembangan Musikal</div>
        <textarea name="catatan_perkembangan_musikal" rows="4"
                  placeholder="Tuliskan catatan perkembangan musikal murid..."
                  class="w-full bg-background border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary transition-colors shadow-sm">{{ old('catatan_perkembangan_musikal', $progressReport->catatan_perkembangan_musikal) }}</textarea>
        @error('catatan_perkembangan_musikal')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="mx-4 mb-4 bg-white border border-outline-variant/30 rounded-2xl p-4 shadow-sm">
        <div class="font-semibold text-sm text-on-surface mb-3">Catatan Lainnya (jika ada):</div>
        <textarea name="catatan_karakter" rows="4"
                  placeholder="Tuliskan catatan lainnya (jika ada)..."
                  class="w-full bg-background border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:border-primary focus:ring-1 focus:ring-primary transition-colors shadow-sm">{{ old('catatan_karakter', $progressReport->catatan_karakter) }}</textarea>
        @error('catatan_karakter')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Kesimpulan Progress --}}
    <div class="mx-4 mb-4 bg-white border border-outline-variant/30 rounded-2xl p-4 shadow-sm">
        <x-kesimpulan-progress-select :value="$progressReport->kesimpulan_progress" />
    </div>

    {{-- Progress percent + bar preview --}}
    <div class="mx-4 mb-4 bg-white border border-outline-variant/30 rounded-2xl p-4 shadow-sm"
         x-data="{ pct: {{ (int) old('progress_percent', $progressReport->progress_percent ?? 0) }} }">
        <div class="font-semibold text-sm text-on-surface mb-3">Progress Keseluruhan (%)</div>
        <div class="flex items-center gap-3 mb-3">
            <input type="number" name="progress_percent" min="0" max="100"
                   x-model.number="pct"
                   class="w-24 bg-background border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-center text-on-surface focus:border-primary focus:ring-1 focus:ring-primary transition-colors shadow-sm">
            <span class="text-sm text-outline">%</span>
        </div>
        <div class="w-full bg-secondary-container rounded-full h-3 overflow-hidden border border-secondary/20 shadow-inner">
            <div class="bg-secondary h-3 rounded-full transition-all"
                 :style="'width:' + Math.min(pct, 100) + '%'"></div>
        </div>
        @error('progress_percent')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Catatan per sesi — read-only --}}
    <div class="mx-4 mb-4 bg-white border border-outline-variant/30 rounded-2xl p-4 shadow-sm">
        <div class="font-semibold text-sm text-on-surface mb-2">Catatan Per Sesi</div>
        <p class="text-xs text-outline mb-4">Diisi per sesi dari dashboard/jadwal — otomatis tampil di sini.</p>
        @forelse($progressReport->sessionNotes->sortBy([['session_date', 'asc'], ['sort_order', 'asc']]) as $note)
            <x-session-note-card
                :student-name="$progressReport->student->full_name"
                :teacher-name="$progressReport->teacher->name"
                :substitute-teacher-name="$note->substitute_teacher_name"
                :session-date="\Carbon\Carbon::parse($note->session_date)->locale('id')->translatedFormat('d F Y')"
                :session-rating="$note->session_rating"
                :material-learned="$note->material_learned"
                :homework-notes="$note->homework_notes"
                :notes="$note->notes"
                :show-empty-badge="true"
            />
        @empty
            <p class="text-sm text-outline">Belum ada sesi HADIR bulan ini.</p>
        @endforelse
    </div>

    {{-- Submit buttons --}}
    <div class="mx-4 mb-3">
        <a href="{{ route('guru.laporan.pdf', $progressReport) }}" target="_blank"
           class="block text-center py-2.5 rounded-xl text-sm font-semibold border border-outline text-on-surface hover:bg-surface-container-low transition-colors shadow-sm">
            View PDF
        </a>
        <p class="text-xs text-outline text-center mt-3">Simpan draft dulu agar perubahan terbaru muncul di preview.</p>
    </div>

    <div class="mx-4 mb-8 flex flex-col md:flex-row gap-3">
        <button type="submit"
                class="flex-1 py-3 rounded-xl font-semibold text-sm border border-secondary text-secondary hover:bg-secondary/10 transition-colors shadow-sm">
            Simpan Draft
        </button>
        <button type="submit" name="submit" value="1"
                class="flex-1 py-3 rounded-xl font-semibold text-sm bg-primary text-on-primary hover:bg-primary-container transition-colors shadow-sm">
            Submit Laporan
        </button>
    </div>
</form>

<script>
function confirmSubmit(event) {
    const isSubmit = event.submitter?.name === 'submit' || event.submitter?.value === '1';
    if (!isSubmit) return true;

    const hasEmptyNotes = @json(
        $progressReport->sessionNotes->contains(fn ($n) =>
            blank($n->material_learned) && blank($n->homework_notes) && blank($n->notes)
        )
    );
    if (hasEmptyNotes && !confirm('Masih ada sesi tanpa catatan. Lanjut submit?')) return false;
    return confirm('Submit laporan? Setelah disubmit, laporan tidak bisa diedit.');
}
</script>

</x-guru-layout>
