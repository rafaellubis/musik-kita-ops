<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h2 class="font-semibold text-xl text-mk-text leading-tight">
                    Open Slot Board
                </h2>
                <div class="text-xs text-mk-dim mt-0.5">Daftar sesi murid izin pending yang belum terjadwal penggantinya</div>
            </div>
            <a href="{{ route('absensi.index') }}"
               class="px-4 py-2 border border-mk-border hover:bg-mk-surface text-mk-muted rounded-lg text-sm font-semibold transition-colors flex items-center gap-2">
                <span>&larr;</span>
                <span>Kembali ke Absensi</span>
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 space-y-6">

        {{-- Penjelasan singkat --}}
        <div class="bg-mk-card border border-mk-borderLight rounded-xl px-4 py-3 text-sm text-mk-muted shadow-sm fade-in-up" style="animation-delay:40ms">
            Daftar sesi <span class="font-semibold text-amber-600">IZIN PENDING</span>
            yang belum ada sesi pengganti. <strong>Isi Slot</strong> untuk murid lain,
            <strong>Jadwalkan Pengganti</strong> untuk murid asli,
            atau pilih <span class="font-semibold">Video</span> jika murid tidak jadi reschedule dan guru memberi video pengganti.
        </div>

        {{-- Flash success / error --}}
        <div id="flash-msg" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-medium"></div>

        @if($slots->isEmpty())
            <div class="text-center py-12 bg-mk-card border border-mk-borderLight rounded-2xl shadow-sm fade-in-up" style="animation-delay:80ms">
                <div class="text-3xl mb-2">✅</div>
                <p class="text-mk-dim text-sm font-medium">Tidak ada Sesi Pending terbuka saat ini.</p>
            </div>
        @else
            <div class="bg-mk-card border border-mk-borderLight shadow-sm rounded-2xl overflow-hidden fade-in-up" style="animation-delay:80ms">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm divide-y divide-mk-borderLight">
                        <thead>
                            <tr class="bg-mk-surface border-b border-mk-borderLight text-left text-[10px] font-semibold uppercase tracking-widest text-mk-dim">
                                <th class="px-4 py-3 text-left">Tanggal & Jam</th>
                                <th class="px-4 py-3 text-left">Guru</th>
                                <th class="px-4 py-3 text-left">Ruang</th>
                                <th class="px-4 py-3 text-left">Murid Asli</th>
                                <th class="px-4 py-3 text-center">Sesi ke-</th>
                                <th class="px-4 py-3 text-center">Pending</th>
                                <th class="px-4 py-3 text-left">Catatan Admin</th>
                                <th class="px-4 py-3 text-left">Saran Guru</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>

                        {{-- Tiap slot pakai <tbody x-data> sendiri agar expand rows masuk scope Alpine --}}
                        @foreach($slots as $slot)
                        @php
                            $hariLalu = (int) \Carbon\Carbon::parse($slot->session_date)->diffInDays(today());
                            $suggestions = $slot->parseTeacherSuggestions();
                            $latest = $slot->latestTeacherSuggestion();
                            $catatanAdmin = $slot->adminNotesWithoutSuggestions();
                            $suggestCount = count($suggestions);
                        @endphp
                        <tbody x-data="{
                                    showAction: null,
                                    showHistory: false,
                                    menuOpen: false,
                                    loading: false,
                                    errorMsg: '',
                                    isiEnrollmentId: '',
                                    isiRoomId: '',
                                    jadwalDate: '{{ $latest['tanggal'] ?? '' }}',
                                    jadwalTime: '{{ $latest['jam'] ?? '' }}',
                                    jadwalRoomId: '',
                                    openMenu() {
                                        this.menuOpen = !this.menuOpen;
                                    },
                                    closeMenu() {
                                        this.menuOpen = false;
                                    },
                                    pickAction(action) {
                                        this.menuOpen = false;
                                        this.errorMsg = '';
                                        if (action === 'isi' || action === 'jadwal') {
                                            this.showAction = (this.showAction === action) ? null : action;
                                        }
                                    },
                                    async postAction(url, payload) {
                                        this.loading  = true;
                                        this.errorMsg = '';
                                        try {
                                            const res = await fetch(url, {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                    'Accept':       'application/json',
                                                },
                                                body: JSON.stringify(payload),
                                            });
                                            const data = await res.json();
                                            if (data.success) {
                                                showFlash(data.message, 'success');
                                                this.$el.remove();
                                            } else {
                                                this.errorMsg = data.message || 'Gagal menyimpan.';
                                            }
                                        } catch (e) {
                                            this.errorMsg = 'Terjadi kesalahan koneksi.';
                                        } finally {
                                            this.loading = false;
                                        }
                                    },
                                    submitIsiSlot() {
                                        if (!this.isiEnrollmentId) { this.errorMsg = 'Pilih murid terlebih dahulu.'; return; }
                                        this.postAction(
                                            '{{ route('absensi.open-slots.assign', $slot) }}',
                                            { enrollment_id: this.isiEnrollmentId, room_id: this.isiRoomId || null }
                                        );
                                    },
                                    submitJadwal() {
                                        if (!this.jadwalDate || !this.jadwalTime) { this.errorMsg = 'Tanggal dan jam wajib diisi.'; return; }
                                        this.postAction(
                                            '{{ route('absensi.open-slots.schedule', $slot) }}',
                                            { replacement_date: this.jadwalDate, replacement_time: this.jadwalTime, room_id: this.jadwalRoomId || null }
                                        );
                                    },
                                    submitBatal() {
                                        const nama = @js($slot->student->full_name);
                                        if (!confirm(`Batalkan izin pending untuk ${nama}? Sesi kembali ke status belum diinput.`)) return;
                                        this.postAction('{{ route('absensi.open-slots.cancel', $slot) }}', {});
                                    },
                                    submitVideo() {
                                        const nama = @js($slot->student->full_name);
                                        const tgl  = @js(\Carbon\Carbon::parse($slot->session_date)->translatedFormat('d M Y'));
                                        const jam  = @js(substr($slot->start_time, 0, 5) . '–' . substr($slot->end_time, 0, 5));
                                        const msg = `Ubah izin pending ${nama} (${tgl}, ${jam}) menjadi Izin Video?\n\nSesi dianggap masuk via video pengganti. Guru mendapat honor penuh. Tidak ada sesi pengganti fisik.`;
                                        if (!confirm(msg)) return;
                                        this.postAction(
                                            '{{ route('absensi.open-slots.video', $slot) }}',
                                            { notes: null }
                                        );
                                    },
                                }"
                                class="divide-y divide-mk-borderLight">

                            {{-- Baris utama --}}
                            <tr class="hover:bg-mk-surface transition-colors">

                                {{-- Tanggal & Jam --}}
                                <td class="px-4 py-3.5 text-mk-text font-bold whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($slot->session_date)->translatedFormat('d M Y') }}
                                    <span class="block text-xs text-mk-dim font-normal mt-0.5">
                                        {{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}
                                    </span>
                                </td>

                                {{-- Guru --}}
                                <td class="px-4 py-3.5 text-mk-text font-medium">
                                    {{ $slot->teacher->name ?? '—' }}
                                </td>

                                {{-- Ruang --}}
                                <td class="px-4 py-3.5 text-mk-muted font-medium">
                                    {{ $slot->room->code ?? '—' }}
                                </td>

                                {{-- Murid Asli --}}
                                <td class="px-4 py-3.5">
                                    <span class="text-mk-text font-semibold">{{ $slot->student->full_name ?? '—' }}</span>
                                    <span class="block text-xs text-mk-dim">{{ $slot->enrollment->package->code ?? '' }}</span>
                                </td>

                                {{-- Sesi ke- --}}
                                <td class="px-4 py-3.5 text-center text-mk-muted font-medium">
                                    {{ $slot->session_sequence ?? '—' }}
                                </td>

                                {{-- Pending sejak --}}
                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full
                                        {{ $hariLalu >= 7 ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-yellow-50 text-yellow-850 border border-yellow-200' }}">
                                        {{ $hariLalu }} hari
                                    </span>
                                </td>

                                {{-- Catatan Admin --}}
                                <td class="px-4 py-3.5 text-xs max-w-[200px]">
                                    @if($catatanAdmin)
                                        <span class="inline-flex items-start gap-1 bg-slate-50 text-slate-700
                                                     border border-slate-200 rounded-lg px-2 py-1">
                                            📝 <span class="break-words font-medium">{{ $catatanAdmin }}</span>
                                        </span>
                                    @else
                                        <span class="text-mk-dim">—</span>
                                    @endif
                                </td>

                                {{-- Saran Guru --}}
                                <td class="px-4 py-3.5 text-xs">
                                    @if($latest)
                                        <div class="space-y-1.5">
                                            <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-800
                                                         border border-amber-200 rounded-lg px-2 py-1 font-semibold">
                                                💬 {{ $latest['label'] }}
                                            </span>
                                            @if($suggestCount > 1)
                                                <div>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                                                                 bg-amber-100 text-amber-800 border border-amber-200">
                                                        Saran ke-{{ $suggestCount }}
                                                    </span>
                                                    <button type="button"
                                                            @click="showHistory = !showHistory"
                                                            class="block text-[10px] text-mk-muted hover:text-mk-accent underline mt-1">
                                                        <span x-text="showHistory ? 'Sembunyikan riwayat' : 'Lihat riwayat'"></span>
                                                    </button>
                                                </div>
                                                <ul x-show="showHistory" x-cloak class="mt-1 space-y-1 pl-1 border-l-2 border-amber-200">
                                                    @foreach($suggestions as $saran)
                                                        <li class="text-mk-muted pl-2 text-[11px]">
                                                            <span class="font-bold text-amber-700">#{{ $saran['index'] }}</span>
                                                            {{ $saran['label'] }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-mk-dim">—</span>
                                    @endif
                                </td>

                                {{-- Aksi --}}
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <div class="relative inline-block text-left" @click.outside="menuOpen = false">
                                        <button type="button"
                                            @click="openMenu()"
                                            :aria-expanded="menuOpen"
                                            aria-haspopup="menu"
                                            :disabled="loading"
                                            class="inline-flex items-center gap-1 text-xs font-bold px-3 py-1.5 rounded-lg border border-mk-border
                                                   text-mk-muted hover:text-mk-text hover:bg-mk-surface disabled:opacity-50 transition-colors">
                                            Opsi
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>

                                        <div x-show="menuOpen"
                                             x-cloak
                                             role="menu"
                                             class="absolute right-0 z-10 mt-1 w-48 origin-top-right rounded-xl bg-white shadow-lg
                                                    border border-gray-200 py-1.5 text-sm">
                                            <button type="button" role="menuitem"
                                                @click="pickAction('isi')"
                                                class="block w-full text-left px-4 py-2 font-medium text-mk-text hover:bg-mk-surface">
                                                Isi Slot
                                            </button>
                                            <button type="button" role="menuitem"
                                                @click="pickAction('jadwal')"
                                                class="block w-full text-left px-4 py-2 font-medium text-mk-text hover:bg-mk-surface">
                                                Jadwalkan Pengganti
                                            </button>
                                            <button type="button" role="menuitem"
                                                @click="closeMenu(); submitVideo()"
                                                class="block w-full text-left px-4 py-2 font-medium text-mk-text hover:bg-mk-surface">
                                                Video
                                            </button>
                                            <button type="button" role="menuitem"
                                                @click="closeMenu(); submitBatal()"
                                                class="block w-full text-left px-4 py-2 font-bold text-red-650 hover:bg-red-50">
                                                Batalkan Pending
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            {{-- Expand: form Isi Slot --}}
                            <tr x-show="showAction === 'isi'" x-cloak class="bg-mk-surface">
                                <td colspan="9" class="px-6 py-4">
                                    <p class="text-xs font-bold text-mk-muted mb-3 uppercase tracking-wider">
                                        Isi slot dengan murid lain — sesi IZIN PENDING murid asli tetap pending
                                    </p>
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div>
                                            <label class="block text-xs text-mk-dim mb-1 font-semibold">Murid (Enrollment)</label>
                                            <select x-model="isiEnrollmentId"
                                                class="border border-mk-border rounded-lg text-sm px-3 py-1.5 min-w-[220px] focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                                                <option value="">— Pilih murid —</option>
                                                @foreach($enrollments as $enr)
                                                    <option value="{{ $enr->id }}">
                                                        {{ $enr->student->full_name ?? '?' }} — {{ $enr->package->code ?? '?' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-mk-dim mb-1 font-semibold">Ruang (opsional)</label>
                                            <select x-model="isiRoomId"
                                                class="border border-mk-border rounded-lg text-sm px-3 py-1.5 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                                                <option value="">— Tanpa ruang —</option>
                                                @foreach($rooms as $r)
                                                    <option value="{{ $r->id }}">{{ $r->code }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button @click="submitIsiSlot()" :disabled="loading"
                                            class="px-4 py-2 rounded-lg text-xs font-bold btn-mk-primary hover:opacity-90 transition-opacity">
                                            <span x-show="!loading">Konfirmasi Isi Slot</span>
                                            <span x-show="loading">Menyimpan…</span>
                                        </button>
                                        <button @click="showAction = null"
                                            class="px-3 py-2 rounded-lg text-xs font-bold border border-mk-border text-mk-dim hover:bg-mk-surface transition-colors">
                                            Batal
                                        </button>
                                    </div>
                                    <p x-show="errorMsg" x-text="errorMsg" class="mt-2 text-xs text-red-600 font-semibold"></p>
                                </td>
                            </tr>

                            {{-- Expand: form Jadwalkan Pengganti --}}
                            <tr x-show="showAction === 'jadwal'" x-cloak class="bg-mk-surface">
                                <td colspan="9" class="px-6 py-4">
                                    <p class="text-xs font-bold text-mk-muted mb-3 uppercase tracking-wider">
                                        Jadwalkan sesi pengganti untuk murid asli
                                    </p>
                                    @if($catatanAdmin)
                                    <div class="mb-3 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 font-medium">
                                        📝 <strong>Catatan Admin:</strong> {{ $catatanAdmin }}
                                    </div>
                                    @endif
                                    @if($latest)
                                    <div class="mb-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 font-medium">
                                        💬 <strong>Saran terbaru dari Guru{{ $suggestCount > 1 ? ' (ke-' . $suggestCount . ')' : '' }}:</strong>
                                        {{ $latest['label'] }} — sudah diisi otomatis di bawah
                                    </div>
                                    @endif
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div>
                                            <label class="block text-xs text-mk-dim mb-1 font-semibold">Tanggal Pengganti</label>
                                            <input type="date" x-model="jadwalDate"
                                                class="border border-mk-border rounded-lg text-sm px-3 py-1.5 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-mk-dim mb-1 font-semibold">Jam Mulai</label>
                                            <input type="time" x-model="jadwalTime"
                                                class="border border-mk-border rounded-lg text-sm px-3 py-1.5 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-mk-dim mb-1 font-semibold">Ruang (opsional)</label>
                                            <select x-model="jadwalRoomId"
                                                class="border border-mk-border rounded-lg text-sm px-3 py-1.5 focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                                                <option value="">— Tanpa ruang —</option>
                                                @foreach($rooms as $r)
                                                    <option value="{{ $r->id }}">{{ $r->code }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button @click="submitJadwal()" :disabled="loading"
                                            class="px-4 py-2 rounded-lg text-xs font-bold btn-mk-primary hover:opacity-90 transition-opacity">
                                            <span x-show="!loading">Jadwalkan</span>
                                            <span x-show="loading">Menyimpan…</span>
                                        </button>
                                        <button @click="showAction = null"
                                            class="px-3 py-2 rounded-lg text-xs font-bold border border-mk-border text-mk-dim hover:bg-mk-surface transition-colors">
                                            Batal
                                        </button>
                                    </div>
                                    <p x-show="errorMsg" x-text="errorMsg" class="mt-2 text-xs text-red-600 font-semibold"></p>
                                </td>
                            </tr>

                        </tbody>
                        @endforeach

                    </table>
                </div>
            </div>
        @endif

    </div>

    <script>
        function showFlash(msg, type) {
            const el = document.getElementById('flash-msg');
            el.textContent = msg;
            el.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium '
                + (type === 'success'
                    ? 'bg-green-50 text-green-800 border border-green-250 shadow-sm'
                    : 'bg-red-50 text-red-800 border border-red-250 shadow-sm');
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 5000);
        }
    </script>
</x-app-layout>
