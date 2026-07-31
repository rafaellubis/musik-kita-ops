<x-guru-layout title="Laporan Progres">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-mk-text">Laporan Progres</h1>
    <p class="text-sm text-mk-muted">Laporan perkembangan murid bulanan</p>
</div>

@php
    $enrollmentOptions = $enrollments->map(fn ($e) => [
        'value' => (string) $e->id,
        'label' => $e->student->full_name . ' — ' . $e->package->code,
    ])->values()->all();
@endphp

{{-- Form buat laporan baru — template otomatis dari paket enrollment --}}
<div class="mx-4 mb-4">
    <details class="bg-mk-card border border-mk-border rounded-xl">
        <summary class="px-4 py-3 font-semibold text-sm text-mk-text cursor-pointer">+ Buat Laporan Baru</summary>
        <div class="px-4 pb-4 pt-2 border-t border-mk-border"
             x-data="{
                map: @js($enrollmentTemplateMap),
                enrollmentId: '{{ old('enrollment_id', '') }}',
                get preview() {
                    return this.enrollmentId && this.map[this.enrollmentId]
                        ? this.map[this.enrollmentId]
                        : null;
                },
                get canSubmit() {
                    return this.enrollmentId && this.preview && this.preview.ok;
                }
             }"
             @searchable-select-changed="if ($event.detail.name === 'enrollment_id') enrollmentId = $event.detail.value">
            <form method="POST" action="{{ route('guru.laporan.store') }}">
                @csrf
                <div class="mb-3">
                    <x-searchable-select
                        name="enrollment_id"
                        label="Kelas / Murid"
                        placeholder="-- Pilih murid --"
                        :selected="old('enrollment_id')"
                        :options="$enrollmentOptions"
                        :required="true"
                        inputClass="w-full bg-white border border-gray-200 rounded-lg text-sm"
                    />
                </div>

                {{-- Preview template otomatis --}}
                <div class="mb-3 rounded-lg px-3 py-2 text-sm border border-mk-border bg-mk-sidebar/20"
                     x-show="preview" x-cloak>
                    <template x-if="preview && preview.ok">
                        <div class="text-mk-text">
                            <span class="text-xs text-mk-muted">Template otomatis:</span>
                            <span class="font-semibold" x-text="preview.name"></span>
                        </div>
                    </template>
                    <template x-if="preview && !preview.ok">
                        <div class="text-red-400 text-xs" x-text="preview.error"></div>
                    </template>
                </div>

                <div class="flex gap-2 mb-3">
                    <div class="flex-1">
                        <label class="block text-xs text-mk-muted mb-1">Bulan</label>
                        <select name="month" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm" required>
                            @foreach(range(1,12) as $m)
                                <option value="{{ $m }}" {{ (int) old('month', now()->month) === $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create()->month($m)->locale('id')->isoFormat('MMMM') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-24">
                        <label class="block text-xs text-mk-muted mb-1">Tahun</label>
                        <input type="number" name="year" value="{{ old('year', now()->year) }}" min="2024" max="2030"
                               class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <button type="submit"
                        class="w-full py-2.5 rounded-xl font-semibold text-sm btn-mk-primary disabled:opacity-40"
                        :disabled="!canSubmit">
                    Buat Laporan
                </button>
            </form>
        </div>
    </details>
</div>

{{-- Daftar laporan --}}
<div class="px-4 space-y-3 pb-24">
    <h2 class="text-xs font-semibold tracking-widest text-mk-muted uppercase">Laporan Saya</h2>

    @forelse($laporan as $r)
        <div class="bg-mk-card border border-mk-border rounded-xl px-4 py-3">
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-semibold text-mk-text text-sm">{{ $r->student->full_name }}</div>
                    <div class="text-xs text-mk-muted mt-0.5">{{ $r->enrollment->package->code }} · {{ $r->namaBulan() }}</div>
                </div>
                <span class="text-xs px-2 py-1 rounded-full font-medium {{ $r->status === 'SUBMITTED' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $r->status === 'SUBMITTED' ? 'Submitted' : 'Draft' }}
                </span>
            </div>
            @if($r->status === 'DRAFT')
                <a href="{{ route('guru.laporan.edit', $r) }}"
                   class="mt-3 block text-center py-2 rounded-lg text-sm font-semibold border border-mk-accent/40 text-mk-accent hover:bg-mk-accent/10">
                    Lanjut Isi →
                </a>
            @else
                <a href="{{ route('guru.laporan.pdf', $r) }}"
                   class="mt-3 block text-center py-2 rounded-lg text-sm font-semibold border border-mk-accent/40 text-mk-accent hover:bg-mk-accent/10">
                    View PDF
                </a>
            @endif
        </div>
    @empty
        <div class="text-center py-8 text-mk-muted text-sm">Belum ada laporan.</div>
    @endforelse
</div>

</x-guru-layout>
