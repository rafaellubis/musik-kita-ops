<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text leading-tight">
            Open Slot Board
        </h2>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">

        {{-- Penjelasan singkat --}}
        <div class="mb-5 bg-mk-card border border-mk-border rounded-lg px-4 py-3 text-sm text-mk-muted">
            Daftar sesi <span class="font-semibold text-amber-600">IZIN PENDING</span>
            yang belum ada sesi pengganti. <strong>Isi Slot</strong> untuk murid lain,
            <strong>Jadwalkan Pengganti</strong> untuk murid asli,
            atau pilih <span class="font-semibold">Video</span> jika murid tidak jadi reschedule dan guru memberi video pengganti.
        </div>

        {{-- Flash success / error --}}
        <div id="flash-msg" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-medium"></div>

        @if($slots->isEmpty())
            <div class="text-center py-12 bg-mk-card border border-mk-border rounded-lg shadow-sm">
                <div class="text-3xl mb-2">✅</div>
                <p class="text-gray-500 text-sm">Tidak ada Sesi Pending  terbuka saat ini.</p>
            </div>
        @else
            <div class="bg-mk-card shadow-sm rounded-lg overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-mk-surface border-b border-mk-border">
                        <tr class="text-mk-dim font-medium text-xs uppercase tracking-wider">
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
                            class="border-b border-mk-border">

                        {{-- Baris utama --}}
                        <tr class="hover:bg-mk-surface transition-colors">

                            {{-- Tanggal & Jam --}}
                            <td class="px-4 py-3 text-mk-text font-medium whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($slot->session_date)->translatedFormat('d M Y') }}
                                <span class="block text-xs text-mk-muted">
                                    {{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}
                                </span>
                            </td>

                            {{-- Guru --}}
                            <td class="px-4 py-3 text-mk-text">
                                {{ $slot->teacher->name ?? '—' }}
                            </td>

                            {{-- Ruang --}}
                            <td class="px-4 py-3 text-mk-muted">
                                {{ $slot->room->code ?? '—' }}
                            </td>

                            {{-- Murid Asli --}}
                            <td class="px-4 py-3">
                                <span class="text-mk-text font-medium">{{ $slot->student->full_name ?? '—' }}</span>
                                <span class="block text-xs text-mk-dim">{{ $slot->enrollment->package->code ?? '' }}</span>
                            </td>

                            {{-- Sesi ke- --}}
                            <td class="px-4 py-3 text-center text-mk-muted">
                                {{ $slot->session_sequence ?? '—' }}
                            </td>

                            {{-- Pending sejak --}}
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs px-2 py-0.5 rounded-full
                                    {{ $hariLalu >= 7 ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ $hariLalu }} hari
                                </span>
                            </td>

                            {{-- Catatan Admin --}}
                            <td class="px-4 py-3 text-xs max-w-[200px]">
                                @if($catatanAdmin)
                                    <span class="inline-flex items-start gap-1 bg-slate-50 text-slate-700
                                                 border border-slate-200 rounded px-2 py-1">
                                        📝 <span class="break-words">{{ $catatanAdmin }}</span>
                                    </span>
                                @else
                                    <span class="text-mk-dim">—</span>
                                @endif
                            </td>

                            {{-- Saran Guru --}}
                            <td class="px-4 py-3 text-xs">
                                @if($latest)
                                    <div class="space-y-1">
                                        <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700
                                                     border border-amber-200 rounded px-2 py-1 font-medium">
                                            💬 {{ $latest['label'] }}
                                        </span>
                                        @if($suggestCount > 1)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                                                         bg-amber-100 text-amber-800 border border-amber-200">
                                                Saran ke-{{ $suggestCount }}
                                            </span>
                                            <button type="button"
                                                    @click="showHistory = !showHistory"
                                                    class="block text-[10px] text-mk-muted hover:text-mk-accent underline">
                                                <span x-text="showHistory ? 'Sembunyikan riwayat' : 'Lihat riwayat'"></span>
                                            </button>
                                            <ul x-show="showHistory" x-cloak class="mt-1 space-y-1 pl-1 border-l-2 border-amber-200">
                                                @foreach($suggestions as $saran)
                                                    <li class="text-mk-muted pl-2">
                                                        <span class="font-medium text-amber-700">#{{ $saran['index'] }}</span>
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
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="relative inline-block text-left" @click.outside="menuOpen = false">
                                    <button type="button"
                                        @click="openMenu()"
                                        :aria-expanded="menuOpen"
                                        aria-haspopup="menu"
                                        :disabled="loading"
                                        class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded border border-mk-border
                                               text-mk-muted hover:text-mk-text hover:bg-mk-surface disabled:opacity-50 transition-colors">
                                        Opsi
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    <div x-show="menuOpen"
                                         x-cloak
                                         role="menu"
                                         class="absolute right-0 z-10 mt-1 w-48 origin-top-right rounded-md bg-white shadow-lg
                                                border border-gray-200 py-1 text-sm">
                                        <button type="button" role="menuitem"
                                            @click="pickAction('isi')"
                                            class="block w-full text-left px-4 py-2 text-mk-text hover:bg-mk-surface">
                                            Isi Slot
                                        </button>
                                        <button type="button" role="menuitem"
                                            @click="pickAction('jadwal')"
                                            class="block w-full text-left px-4 py-2 text-mk-text hover:bg-mk-surface">
                                            Jadwalkan Pengganti
                                        </button>
                                        <button type="button" role="menuitem"
                                            @click="closeMenu(); submitVideo()"
                                            class="block w-full text-left px-4 py-2 text-mk-text hover:bg-mk-surface">
                                            Video
                                        </button>
                                        <button type="button" role="menuitem"
                                            @click="closeMenu(); submitBatal()"
                                            class="block w-full text-left px-4 py-2 text-red-600 hover:bg-red-50">
                                            Batalkan Pending
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        {{-- Expand: form Isi Slot --}}
                        <tr x-show="showAction === 'isi'" x-cloak class="bg-mk-surface">
                            <td colspan="9" class="px-6 py-4">
                                <p class="text-xs font-semibold text-mk-muted mb-3 uppercase tracking-wider">
                                    Isi slot dengan murid lain — sesi IZIN PENDING murid asli tetap pending
                                </p>
                                <div class="flex flex-wrap items-end gap-3">
                                    <div>
                                        <label class="block text-xs text-mk-dim mb-1">Murid (Enrollment)</label>
                                        <select x-model="isiEnrollmentId"
                                            class="border border-gray-300 rounded text-sm px-3 py-1.5 min-w-[220px]">
                                            <option value="">— Pilih murid —</option>
                                            @foreach($enrollments as $enr)
                                                <option value="{{ $enr->id }}">
                                                    {{ $enr->student->full_name ?? '?' }} — {{ $enr->package->code ?? '?' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-mk-dim mb-1">Ruang (opsional)</label>
                                        <select x-model="isiRoomId"
                                            class="border border-gray-300 rounded text-sm px-3 py-1.5">
                                            <option value="">— Tanpa ruang —</option>
                                            @foreach($rooms as $r)
                                                <option value="{{ $r->id }}">{{ $r->code }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button @click="submitIsiSlot()" :disabled="loading"
                                        class="px-4 py-1.5 rounded text-sm font-medium bg-yellow-500 text-white
                                               hover:bg-yellow-600 disabled:opacity-50 transition-colors">
                                        <span x-show="!loading">Konfirmasi Isi Slot</span>
                                        <span x-show="loading">Menyimpan…</span>
                                    </button>
                                    <button @click="showAction = null"
                                        class="px-3 py-1.5 rounded text-sm text-mk-muted hover:text-mk-text transition-colors">
                                        Batal
                                    </button>
                                </div>
                                <p x-show="errorMsg" x-text="errorMsg" class="mt-2 text-xs text-red-600"></p>
                            </td>
                        </tr>

                        {{-- Expand: form Jadwalkan Pengganti --}}
                        <tr x-show="showAction === 'jadwal'" x-cloak class="bg-mk-surface">
                            <td colspan="9" class="px-6 py-4">
                                <p class="text-xs font-semibold text-mk-muted mb-3 uppercase tracking-wider">
                                    Jadwalkan sesi pengganti untuk murid asli
                                </p>
                                @if($catatanAdmin)
                                <div class="mb-3 px-3 py-2 bg-slate-50 border border-slate-200 rounded text-xs text-slate-700">
                                    📝 <strong>Catatan Admin:</strong> {{ $catatanAdmin }}
                                </div>
                                @endif
                                @if($latest)
                                <div class="mb-3 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                    💬 <strong>Saran terbaru dari Guru{{ $suggestCount > 1 ? ' (ke-' . $suggestCount . ')' : '' }}:</strong>
                                    {{ $latest['label'] }} — sudah diisi otomatis di bawah
                                </div>
                                @endif
                                <div class="flex flex-wrap items-end gap-3">
                                    <div>
                                        <label class="block text-xs text-mk-dim mb-1">Tanggal Pengganti</label>
                                        <input type="date" x-model="jadwalDate"
                                            class="border border-gray-300 rounded text-sm px-3 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-mk-dim mb-1">Jam Mulai</label>
                                        <input type="time" x-model="jadwalTime"
                                            class="border border-gray-300 rounded text-sm px-3 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-mk-dim mb-1">Ruang (opsional)</label>
                                        <select x-model="jadwalRoomId"
                                            class="border border-gray-300 rounded text-sm px-3 py-1.5">
                                            <option value="">— Tanpa ruang —</option>
                                            @foreach($rooms as $r)
                                                <option value="{{ $r->id }}">{{ $r->code }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button @click="submitJadwal()" :disabled="loading"
                                        class="px-4 py-1.5 rounded text-sm font-medium bg-green-600 text-white
                                               hover:bg-green-700 disabled:opacity-50 transition-colors">
                                        <span x-show="!loading">Jadwalkan</span>
                                        <span x-show="loading">Menyimpan…</span>
                                    </button>
                                    <button @click="showAction = null"
                                        class="px-3 py-1.5 rounded text-sm text-mk-muted hover:text-mk-text transition-colors">
                                        Batal
                                    </button>
                                </div>
                                <p x-show="errorMsg" x-text="errorMsg" class="mt-2 text-xs text-red-600"></p>
                            </td>
                        </tr>

                    </tbody>
                    @endforeach

                </table>
            </div>
        @endif

    </div>

    <script>
        function showFlash(msg, type) {
            const el = document.getElementById('flash-msg');
            el.textContent = msg;
            el.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium '
                + (type === 'success'
                    ? 'bg-green-100 text-green-800 border border-green-200'
                    : 'bg-red-100 text-red-800 border border-red-200');
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 5000);
        }
    </script>
</x-app-layout>
