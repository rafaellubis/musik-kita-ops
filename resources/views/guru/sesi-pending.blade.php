<x-guru-layout title="Sesi Pending">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-mk-text">Sesi Pending</h1>
    <p class="text-sm text-mk-muted">
        Sesi izin yang belum mendapat jadwal pengganti.
        Anda bisa menyarankan tanggal ke Admin.
    </p>
</div>

{{-- ===== DAFTAR SESI PENDING ===== --}}
<div class="px-4 pb-24 space-y-3">

    @forelse($sesiPending as $sesi)
        @php
            $hariPending = \Carbon\Carbon::parse($sesi->session_date)->diffInDays(today());
            $suggestUrl  = route('guru.sesi-pending.suggest', $sesi);
        @endphp

        {{-- Card satu sesi IZIN_PENDING — x-data membawa semua state + method fetch --}}
        <div x-data="{
                open:      false,
                submitted: false,
                loading:   false,
                message:   '',
                tanggal:   '',
                jam:       '',
                catatan:   '',
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
                            this.submitted = true;
                            this.open      = false;
                            this.message   = json.message ?? 'Saran terkirim ke Admin.';
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
             class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

            {{-- Header card: nama murid, detail, badge hari pending --}}
            <div class="flex items-start justify-between px-4 py-3 border-b border-gray-100">
                <div class="flex-1 min-w-0 pr-3">
                    <div class="font-semibold text-mk-text text-sm">{{ $sesi->student->full_name }}</div>
                    <div class="text-xs text-mk-muted mt-0.5">
                        Sesi ke-{{ $sesi->session_sequence ?? '?' }}
                        · {{ $sesi->enrollment?->package?->code ?? '—' }}
                        · {{ \Carbon\Carbon::parse($sesi->session_date)->locale('id')->isoFormat('D MMMM Y') }}
                    </div>
                </div>
                {{-- Badge berapa hari sudah pending — merah jika > 14 hari, kuning jika <= 14 --}}
                <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                    {{ $hariPending > 14 ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-700' }}">
                    {{ $hariPending }} hari
                </span>
            </div>

            {{-- Badan card --}}
            <div class="px-4 py-3">

                {{-- Pesan sukses setelah submit --}}
                <div x-show="submitted" x-transition
                     class="flex items-center gap-2 rounded-xl bg-green-50 border border-green-100 px-3 py-2.5">
                    <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-green-700 text-sm font-medium" x-text="message"></span>
                </div>

                {{-- Tombol toggle accordion — sembunyikan setelah berhasil submit --}}
                <button x-show="!submitted"
                        @click="open = !open"
                        type="button"
                        class="w-full flex items-center justify-between py-1.5 text-sm font-medium
                               text-mk-text hover:text-mk-accent transition-colors">
                    <span>Suggest tanggal ke Admin</span>
                    <svg :class="open ? 'rotate-180' : ''"
                         class="w-4 h-4 text-mk-muted transition-transform duration-200"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Form suggest tanggal (accordion body) --}}
                <div x-show="open" x-transition class="mt-3 space-y-3">

                    {{-- Input tanggal --}}
                    <div>
                        <label class="block text-xs font-medium text-mk-muted mb-1">
                            Tanggal Usulan <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               x-model="tanggal"
                               min="{{ today()->toDateString() }}"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-mk-accent/40">
                    </div>

                    {{-- Pilih jam: dropdown 07:00 s/d 21:00 per 30 menit --}}
                    <div>
                        <label class="block text-xs font-medium text-mk-muted mb-1">
                            Jam Mulai <span class="text-red-500">*</span>
                        </label>
                        <select x-model="jam"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                                       focus:outline-none focus:ring-2 focus:ring-mk-accent/40">
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
                        <label class="block text-xs font-medium text-mk-muted mb-1">
                            Catatan (opsional)
                        </label>
                        <input type="text"
                               x-model="catatan"
                               maxlength="200"
                               placeholder="Misal: Murid bilang bisa Rabu pagi"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-mk-accent/40">
                    </div>

                    {{-- Tombol kirim --}}
                    <button @click="kirimSaran()"
                            :disabled="!tanggal || !jam || loading"
                            type="button"
                            class="w-full py-2.5 rounded-xl font-semibold text-sm transition-all
                                   disabled:opacity-40 disabled:cursor-not-allowed"
                            style="background-color:#D4A853;color:#1A1000;">
                        <span x-show="!loading">Kirim Saran ke Admin</span>
                        <span x-show="loading">Mengirim…</span>
                    </button>

                </div>{{-- /accordion --}}
            </div>{{-- /card body --}}
        </div>{{-- /card --}}

    @empty
        {{-- State kosong --}}
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-12 text-center">
            <div class="text-4xl mb-3">✅</div>
            <div class="font-medium text-mk-text text-sm">Tidak ada sesi pending.</div>
            <div class="text-xs text-mk-muted mt-1">
                Semua izin murid sudah mendapat jadwal pengganti.
            </div>
        </div>
    @endforelse

</div>

</x-guru-layout>
