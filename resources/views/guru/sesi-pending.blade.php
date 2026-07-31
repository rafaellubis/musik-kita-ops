<x-guru-layout title="Sesi Pending">

<div class="max-w-2xl mx-auto px-4 lg:px-8 pt-6 pb-2">
    <h1 class="text-xl lg:text-2xl font-bold text-primary">Sesi Pending</h1>
    <p class="text-xs lg:text-sm text-mk-muted mt-1">
        Sesi izin yang belum mendapat jadwal pengganti.
        Anda bisa menyarankan tanggal ke Admin.
    </p>
</div>

{{-- ===== DAFTAR SESI PENDING ===== --}}
<div class="max-w-2xl mx-auto px-4 pb-24 space-y-4">

    @forelse($sesiPending as $sesi)
        @php
            $hariPending = \Carbon\Carbon::parse($sesi->session_date)->diffInDays(today());
            $suggestUrl  = route('guru.sesi-pending.suggest', $sesi);
            $suggestions = $sesi->parseTeacherSuggestions();
            $suggestCount = count($suggestions);
        @endphp

        {{-- Card satu sesi IZIN_PENDING — x-data membawa semua state + method fetch --}}
        <div x-data="{
                open:          false,
                showHistory:   {{ $suggestCount > 0 ? 'true' : 'false' }},
                showSuccess:   false,
                loading:       false,
                message:       '',
                suggestCount:  {{ $suggestCount }},
                history:       @js($suggestions),
                tanggal:       '',
                jam:           '',
                catatan:       '',
                async kirimSaran() {
                    if (!this.tanggal || !this.jam) return;
                    this.loading = true;
                    try {
                        const res = await fetch('{{ $suggestUrl }}', {
                            method:  'POST',
                            headers: {
                                'Content-Type':     'application/json',
                                'X-CSRF-TOKEN':     document.querySelector('meta[name=csrf-token]').content,
                                'Accept':           'application/json',
                            },
                            body: JSON.stringify({
                                tanggal: this.tanggal,
                                jam:     this.jam,
                                catatan: this.catatan,
                            }),
                        });
                        const json = await res.json();
                        if (json.success) {
                            this.suggestCount = json.suggestion_count ?? (this.suggestCount + 1);
                            if (json.latest) {
                                this.history = this.history.filter(h => h.index !== json.latest.index);
                                this.history.push(json.latest);
                                this.history.sort((a, b) => a.index - b.index);
                            }
                            this.tanggal = '';
                            this.jam     = '';
                            this.catatan = '';
                            this.open    = false;
                            this.showHistory = true;
                            this.message = json.message ?? 'Saran terkirim ke Admin.';
                            this.showSuccess = true;
                            setTimeout(() => { this.showSuccess = false; }, 4000);
                        } else {
                            alert(json.message ?? 'Gagal mengirim saran.');
                        }
                    } catch(e) {
                        alert('Terjadi kesalahan jaringan.');
                    } finally {
                        this.loading = false;
                    }
                }
            }"
             class="bg-white rounded-2xl border border-[#E0EAE5] shadow-[0_2px_12px_rgba(74,14,14,0.04)] overflow-hidden">

            {{-- Header card: nama murid, detail, badge hari pending --}}
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2 px-4 py-4 border-b border-[#E0EAE5]">
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-primary text-base">{{ $sesi->student->full_name }}</div>
                    <div class="text-xs text-mk-muted mt-1 flex flex-wrap items-center gap-1.5">
                        <span>Sesi ke-{{ $sesi->session_sequence ?? '?' }}</span>
                        <span class="text-gray-300">•</span>
                        <span class="font-medium">{{ $sesi->enrollment?->package?->code ?? '—' }}</span>
                        <span class="text-gray-300">•</span>
                        <span>{{ \Carbon\Carbon::parse($sesi->session_date)->locale('id')->isoFormat('D MMMM Y') }}</span>
                    </div>
                </div>
                <div class="flex items-center sm:flex-col sm:items-end gap-1.5 shrink-0 mt-1 sm:mt-0">
                    {{-- Badge berapa hari sudah pending — merah jika > 14 hari, kuning jika <= 14 --}}
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold tracking-wide border
                        {{ $hariPending > 14 ? 'bg-error-container text-on-error-container border-error/15' : 'bg-[#FFF9E6] text-amber-800 border-amber-200/50' }}">
                        {{ $hariPending }} hari pending
                    </span>
                    {{-- Badge jumlah suggest guru --}}
                    <span x-show="suggestCount > 0" x-cloak
                          class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold tracking-wide
                                 bg-secondary-container/50 text-on-secondary-container border border-secondary/20">
                        <span x-text="'Saran ke-' + suggestCount"></span>
                    </span>
                </div>
            </div>

            {{-- Badan card --}}
            <div class="px-4 py-4 space-y-4">

                {{-- Pesan sukses sementara setelah submit --}}
                <div x-show="showSuccess" x-transition x-cloak
                     class="flex items-center gap-2.5 rounded-lg bg-secondary-container/45 border border-secondary/20 px-3.5 py-3">
                    <svg class="w-4 h-4 text-secondary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-on-secondary-container text-xs font-semibold tracking-wide" x-text="message"></span>
                </div>

                {{-- Riwayat saran guru --}}
                <div x-show="history.length > 0" x-cloak class="bg-background/40 border border-[#E0EAE5] rounded-xl p-3">
                    <button type="button"
                            @click="showHistory = !showHistory"
                            class="flex items-center justify-between w-full text-xs font-bold text-mk-muted hover:text-primary transition-colors">
                        <span x-text="'Riwayat Saran (' + history.length + ')'"></span>
                        <svg :class="showHistory ? 'rotate-180' : ''"
                             class="w-3.5 h-3.5 transition-transform duration-200"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <ul x-show="showHistory" x-transition class="mt-3.5 space-y-2 border-l-2 border-[#D1EADF] pl-3.5">
                        <template x-for="item in history" :key="item.index">
                            <li class="text-xs text-mk-text flex items-start gap-1">
                                <span class="font-bold text-secondary shrink-0" x-text="'#' + item.index"></span>
                                <span class="leading-relaxed" x-text="item.label"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Tombol toggle accordion form suggest --}}
                <button @click="open = !open"
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg bg-secondary-container/10 border border-secondary/10 hover:bg-secondary-container/20 transition-all">
                    <span class="text-sm font-bold text-secondary" x-text="suggestCount > 0 ? 'Kirim Usulan Tanggal Baru' : 'Kirim Usulan Tanggal ke Admin'"></span>
                    <svg :class="open ? 'rotate-180' : ''"
                         class="w-4 h-4 text-secondary transition-transform duration-200"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Form suggest tanggal (accordion body) --}}
                <div x-show="open" x-transition class="space-y-4 pt-2">

                    {{-- Input tanggal --}}
                    <div>
                        <label class="block text-[10px] font-bold tracking-wider text-mk-muted uppercase mb-1.5">
                            Tanggal Usulan <span class="text-error font-sans">*</span>
                        </label>
                        <input type="date"
                               x-model="tanggal"
                               min="{{ today()->toDateString() }}"
                               class="w-full bg-white border border-[#D1EADF] rounded-lg px-3.5 py-2.5 text-sm text-primary
                                      focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all">
                    </div>

                    {{-- Pilih jam: dropdown 07:00 s/d 21:00 per 30 menit --}}
                    <div>
                        <label class="block text-[10px] font-bold tracking-wider text-mk-muted uppercase mb-1.5">
                            Jam Mulai <span class="text-error font-sans">*</span>
                        </label>
                        <select x-model="jam"
                                class="w-full bg-white border border-[#D1EADF] rounded-lg px-3.5 py-2.5 text-sm text-primary
                                       focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all">
                            <option value="">-- Pilih jam --</option>
                            @for($h = 7; $h <= 21; $h++)
                                @foreach(['00', '30'] as $m)
                                    @php $slot = sprintf('%02d:%s', $h, $m); @endphp
                                    @if(!($h === 21 && $m === '30'))
                                        <option value="{{ $slot }}">{{ $slot }}</option>
                                    @endif
                                @endforeach
                            @endfor
                        </select>
                    </div>

                    {{-- Catatan opsional --}}
                    <div>
                        <label class="block text-[10px] font-bold tracking-wider text-mk-muted uppercase mb-1.5">
                            Catatan (opsional)
                        </label>
                        <input type="text"
                               x-model="catatan"
                               maxlength="200"
                               placeholder="Misal: Murid bilang bisa Rabu pagi"
                               class="w-full bg-white border border-[#D1EADF] rounded-lg px-3.5 py-2.5 text-sm text-primary placeholder-[#A8C4B8]
                                      focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all">
                    </div>

                    {{-- Tombol kirim --}}
                    <button @click="kirimSaran()"
                            :disabled="!tanggal || !jam || loading"
                            type="button"
                            class="w-full py-2.5 rounded-lg font-bold text-sm transition-all shadow-sm
                                   bg-primary hover:bg-[#3f0709] text-white
                                   disabled:opacity-40 disabled:cursor-not-allowed disabled:bg-primary/50">
                        <span x-show="!loading">Kirim Saran ke Admin</span>
                        <span x-show="loading" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            Mengirim…
                        </span>
                    </button>

                </div>{{-- /accordion --}}
            </div>{{-- /card body --}}
        </div>{{-- /card --}}

    @empty
        {{-- State kosong --}}
        <div class="bg-white rounded-2xl border border-[#E0EAE5] shadow-[0_2px_12px_rgba(74,14,14,0.04)] px-4 py-12 text-center">
            <div class="text-4xl mb-3">✅</div>
            <div class="font-bold text-primary text-sm">Tidak ada sesi pending.</div>
            <div class="text-xs text-mk-muted mt-1">
                Semua izin murid sudah mendapat jadwal pengganti.
            </div>
        </div>
    @endforelse

</div>

</x-guru-layout>
