<x-guru-layout title="Isi Laporan">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-base font-semibold text-mk-text">{{ $progressReport->student->full_name }}</h1>
    <p class="text-sm text-mk-muted">{{ $progressReport->enrollment->package->code }} · {{ $progressReport->namaBulan() }}</p>
</div>

<form method="POST" action="{{ route('guru.laporan.update', $progressReport) }}" onsubmit="return confirmSubmit(event)">
    @csrf @method('PUT')

    {{-- Checklist per seksi — seksi DUO hanya untuk paket DUO --}}
    @foreach($progressReport->template->sections as $section)
        @php
            if (! $progressReport->enrollment->package->isDuo() && str_contains($section->title, 'Belajar Berduo')) {
                continue;
            }
            $sectionRecord = $progressReport->sections->firstWhere('report_template_section_id', $section->id);
        @endphp
        <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-mk-border bg-mk-sidebar/30">
                <div class="font-semibold text-sm text-mk-text">{{ $section->title }}</div>
            </div>
            <div class="px-4 pt-3">
                <textarea name="section_summary[{{ $section->id }}]" rows="2"
                          placeholder="Ringkasan singkat seksi ini (opsional)..."
                          class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old("section_summary.{$section->id}", $sectionRecord?->summary) }}</textarea>
            </div>
            <div class="px-4 pb-3 pt-2 space-y-2">
                @foreach($section->items as $item)
                    @php $itemRecord = $progressReport->items->firstWhere('report_template_item_id', $item->id); @endphp
                    <label class="flex items-center gap-3 text-sm text-mk-text cursor-pointer">
                        <input type="checkbox" name="checked_items[]" value="{{ $item->id }}"
                               class="w-4 h-4 rounded"
                               {{ $itemRecord?->is_checked ? 'checked' : '' }}>
                        <span>{{ $item->label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Repertoar --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4"
         x-data="{ lagu: {{ json_encode(old('repertoire', $progressReport->repertoire ?? [])) }}, tambah() { this.lagu.push(''); }, hapus(i) { this.lagu.splice(i, 1); } }">
        <div class="font-semibold text-sm text-mk-text mb-2">Repertoar (Lagu yang Dipelajari)</div>
        <template x-for="(l, i) in lagu" :key="i">
            <div class="flex gap-2 mb-2">
                <input type="text" :name="'repertoire[' + i + ']'" x-model="lagu[i]"
                       placeholder="Judul lagu..."
                       class="flex-1 bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
                <button type="button" @click="hapus(i)" class="text-red-400 hover:text-red-600 text-lg leading-none px-1">×</button>
            </div>
        </template>
        <button type="button" @click="tambah()" class="text-mk-accent text-sm hover:underline">+ Tambah lagu</button>
    </div>

    {{-- Highlight --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-2">Highlight Pencapaian</div>
        <textarea name="highlight" rows="4" placeholder="Ceritakan perkembangan murid yang menonjol bulan ini..."
                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old('highlight', $progressReport->highlight) }}</textarea>
    </div>

    {{-- Catatan per sesi (read-only, dari dashboard/jadwal) --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-1">Catatan Per Sesi</div>
        <p class="text-xs text-mk-muted mb-3">Diisi per sesi dari dashboard/jadwal — otomatis tampil di sini.</p>
        @forelse($progressReport->sessionNotes->sortBy([['session_date', 'asc'], ['sort_order', 'asc']]) as $note)
            <x-session-note-card
                :student-name="$progressReport->student->full_name"
                :teacher-name="$progressReport->teacher->name"
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

    {{-- Catatan akhir --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-2">Catatan Akhir (Pesan ke Murid/Orangtua)</div>
        <textarea name="summary_notes" rows="3" placeholder="Saran dan pesan untuk latihan ke depan..."
                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old('summary_notes', $progressReport->summary_notes) }}</textarea>
    </div>

    {{-- Target --}}
    <div class="mx-4 mb-6 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-2">Target Bulan Depan</div>
        <textarea name="target_notes" rows="3" placeholder="Target yang ingin dicapai murid bulan berikutnya..."
                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old('target_notes', $progressReport->target_notes) }}</textarea>
    </div>

    {{-- Tombol --}}
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
    const submitter = event.submitter;
    const isSubmit = submitter && (submitter.name === 'submit' || submitter.value === '1');
    if (!isSubmit) {
        return true;
    }

    const hasEmptyNotes = @json(
        $progressReport->sessionNotes->contains(fn ($n) =>
            blank($n->material_learned) && blank($n->homework_notes) && blank($n->notes)
        )
    );
    if (hasEmptyNotes && !confirm('Masih ada sesi tanpa catatan. Lanjut submit?')) {
        return false;
    }

    return confirm('Submit laporan? Setelah disubmit, laporan tidak bisa diedit.');
}
</script>

</x-guru-layout>
