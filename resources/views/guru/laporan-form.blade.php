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
    <h1 class="text-base font-semibold text-mk-text">{{ $progressReport->student->full_name }}</h1>
    <p class="text-sm text-mk-muted">{{ $progressReport->enrollment->package->code }} · {{ $progressReport->namaBulan() }}</p>
</div>

<form method="POST" action="{{ route('guru.laporan.update', $progressReport) }}" onsubmit="return confirmSubmit(event)">
    @csrf @method('PUT')

    {{-- Header info + Rating Anak --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <table class="w-full text-sm">
            <tr><td class="text-mk-muted w-36 py-0.5">Nama</td><td class="py-0.5">: <strong>{{ $progressReport->student->full_name }}</strong></td></tr>
            <tr><td class="text-mk-muted py-0.5">Instrumen</td><td class="py-0.5">: {{ $progressReport->enrollment->package->instrument->name }}</td></tr>
            <tr><td class="text-mk-muted py-0.5">Guru Pengajar</td><td class="py-0.5">: {{ $progressReport->teacher->name }}</td></tr>
            <tr><td class="text-mk-muted py-0.5">Bulan</td><td class="py-0.5">: {{ $progressReport->namaBulan() }}</td></tr>
            <tr>
                <td class="text-mk-muted py-0.5">Rating Anak</td>
                <td class="py-0.5">: <span class="text-yellow-500 tracking-wide">{{ $headerStars }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Kehadiran & Materi Minggu 1–4 --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-3">Kehadiran dan Materi yang Dipelajari</div>
        <div class="space-y-3">
            @foreach ($mingguLabels as $seq => $label)
                <div class="flex items-start gap-3">
                    <span class="text-sm text-mk-muted w-20 shrink-0 pt-2">{{ $label }}</span>
                    <div class="flex-1 border border-[#E8D5A0] rounded-lg bg-white px-3 py-2 text-sm min-h-[2.5rem] text-mk-text whitespace-pre-line">
                        {{ $weekly[$seq] ?? '—' }}
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-mk-muted mt-2">Diisi otomatis dari catatan sesi. Edit via Dashboard → sesi terkait.</p>
    </div>

    {{-- Perkembangan bulanan — 4 star ratings --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-3">
            Perkembangan {{ $progressReport->student->full_name }} Selama Les di Bulan {{ $progressReport->namaBulan() }}
        </div>
        <div class="space-y-3">
            @foreach (\App\Models\ProgressReport::monthlyRatingFields() as $field)
                <div class="flex items-start gap-3">
                    <span class="text-sm font-medium text-mk-text w-28 shrink-0 pt-2">{{ $field['label'] }}</span>
                    <div class="w-32 shrink-0">
                        <select name="{{ $field['rating'] }}"
                                class="w-full bg-white border border-gray-200 rounded-lg px-2 py-2 text-sm text-gray-900">
                            <option value="">—</option>
                            @for ($i = 1; $i <= 5; $i++)
                                <option value="{{ $i }}" @selected((int) old($field['rating'], $progressReport->{$field['rating']}) === $i)>
                                    {{ str_repeat('★', $i) }}{{ str_repeat('☆', 5 - $i) }}
                                </option>
                            @endfor
                        </select>
                        @error($field['rating'])<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex-1 min-w-0">
                        <textarea name="{{ $field['catatan'] }}" rows="2"
                                  placeholder="Catatan (opsional)..."
                                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old($field['catatan'], $progressReport->{$field['catatan']}) }}</textarea>
                        @error($field['catatan'])<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Catatan naratif --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-2">Catatan Guru Terhadap Perkembangan Musikal</div>
        <textarea name="catatan_perkembangan_musikal" rows="4"
                  placeholder="Tuliskan catatan perkembangan musikal murid..."
                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old('catatan_perkembangan_musikal', $progressReport->catatan_perkembangan_musikal) }}</textarea>
        @error('catatan_perkembangan_musikal')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-2">Catatan Lainnya (jika ada):</div>
        <textarea name="catatan_karakter" rows="4"
                  placeholder="Tuliskan catatan lainnya (jika ada)..."
                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old('catatan_karakter', $progressReport->catatan_karakter) }}</textarea>
        @error('catatan_karakter')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Kesimpulan Progress --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <x-kesimpulan-progress-select :value="$progressReport->kesimpulan_progress" />
    </div>

    {{-- Progress percent + bar preview --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4"
         x-data="{ pct: {{ (int) old('progress_percent', $progressReport->progress_percent ?? 0) }} }">
        <div class="font-semibold text-sm text-mk-text mb-2">Progress Keseluruhan (%)</div>
        <div class="flex items-center gap-3 mb-2">
            <input type="number" name="progress_percent" min="0" max="100"
                   x-model.number="pct"
                   class="w-24 bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-center">
            <span class="text-sm text-mk-muted">%</span>
        </div>
        <div class="w-full bg-[#F0E4C0] rounded-full h-3 overflow-hidden border border-[#C8A870]">
            <div class="bg-[#C8A870] h-3 rounded-full transition-all"
                 :style="'width:' + Math.min(pct, 100) + '%'"></div>
        </div>
        @error('progress_percent')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Catatan per sesi — read-only --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-1">Catatan Per Sesi</div>
        <p class="text-xs text-mk-muted mb-3">Diisi per sesi dari dashboard/jadwal — otomatis tampil di sini.</p>
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
            <p class="text-sm text-mk-muted">Belum ada sesi HADIR bulan ini.</p>
        @endforelse
    </div>

    {{-- Submit buttons --}}
    <div class="mx-4 mb-3">
        <a href="{{ route('guru.laporan.pdf', $progressReport) }}" target="_blank"
           class="block text-center py-2.5 rounded-xl text-sm font-semibold border border-mk-accent/40 text-mk-accent hover:bg-mk-accent/10">
            View PDF
        </a>
        <p class="text-xs text-mk-muted text-center mt-2">Simpan draft dulu agar perubahan terbaru muncul di preview.</p>
    </div>

    <div class="mx-4 mb-8 flex gap-3">
        <button type="submit"
                class="flex-1 py-3 rounded-xl font-semibold text-sm border border-mk-accent/40 text-mk-accent hover:bg-mk-accent/10">
            Simpan Draft
        </button>
        <button type="submit" name="submit" value="1"
                class="flex-1 py-3 rounded-xl font-semibold text-sm btn-mk-primary">
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
