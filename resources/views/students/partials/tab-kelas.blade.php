{{-- Tab Kelas — Manajemen kelas berjalan dan riwayat kelas murid --}}
{{-- Variabel yang dibutuhkan: $student, $activeEnrollments, $historyEnrollments --}}

<div class="space-y-4">

    {{-- Notifikasi konfirmasi swap kelas utama (muncul setelah DELETE enrollment primer dengan >1 kelas aktif) --}}
    @if(session('confirm_primary_swap') && auth()->user()->hasAnyRole(['Owner', 'Admin']))
        @php $swap = session('confirm_primary_swap'); @endphp
        <div class="rounded-xl p-4"
             style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.25)">
            <p class="text-sm font-semibold mb-3" style="color:#FBBF24">
                Kelas yang dihentikan adalah <strong>Kelas Utama</strong>.
                Pilih kelas pengganti sebagai kelas utama baru:
            </p>
            <form method="POST"
                  action="{{ route('students.enrollments.destroy', [$student, $swap['enrollment_id']]) }}">
                @csrf @method('DELETE')
                <div class="flex items-center gap-3">
                    <select name="new_primary_enrollment_id"
                            class="flex-1 rounded-lg text-sm px-3 py-2"
                            style="border:1px solid rgba(251,191,36,0.3)">
                        @foreach($swap['other_actives'] as $other)
                            <option value="{{ $other['id'] }}">
                                {{ $other['package_code'] ?? $other['package_id'] }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-semibold"
                            style="background:rgba(251,191,36,0.2);color:#FBBF24">
                        Hentikan &amp; Ganti Utama
                    </button>
                    <a href="{{ route('students.show', $student) }}"
                       class="px-4 py-2 text-sm text-gray-500 hover:underline">Batal</a>
                </div>
            </form>
        </div>
    @endif

    {{-- ===== KELAS BERJALAN ===== --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

        {{-- Header section kelas berjalan --}}
        <div class="px-5 py-3.5 flex items-center justify-between border-b border-gray-100">
            <div class="text-[10px] uppercase tracking-widest font-semibold" style="color:#D4A853">
                Kelas Berjalan
                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold"
                      style="background:rgba(212,168,83,0.12);color:#D4A853">
                    {{ $activeEnrollments->count() }}
                </span>
            </div>
            @if(auth()->user()->hasAnyRole(['Owner', 'Admin']))
                <button type="button"
                        onclick="document.getElementById('modal-tambah-kelas').classList.remove('hidden')"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                        style="background:rgba(212,168,83,0.15);color:#D4A853;border:1px solid rgba(212,168,83,0.3)">
                    + Tambah Kelas
                </button>
            @endif
        </div>

        {{-- Daftar kelas aktif --}}
        @forelse($activeEnrollments as $enrollment)
            @php
                // Ambil jadwal pertama yang aktif untuk tampilan ringkasan
                $jadwal = $enrollment->schedules->first();
                $hariMap = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            @endphp
            <div class="px-5 py-4 flex items-start gap-4 border-b border-gray-100 last:border-0">

                {{-- Ikon instrumen --}}
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base flex-shrink-0 mt-0.5"
                     style="background:rgba(212,168,83,0.1)">
                    🎵
                </div>

                {{-- Informasi kelas --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-800">
                            {{ $enrollment->package->instrument->name ?? '—' }}
                            &nbsp;·&nbsp;
                            <span class="font-mono">{{ $enrollment->package->code ?? '—' }}</span>
                        </span>
                        @if($enrollment->is_primary)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                  style="background:rgba(212,168,83,0.15);color:#D4A853">
                                ★ Kelas Utama
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 mt-1 space-y-0.5">
                        <div>
                            Guru: <span class="text-gray-700">{{ $enrollment->teacher->name ?? '—' }}</span>
                            @if($jadwal)
                                &nbsp;·&nbsp;
                                {{ $hariMap[$jadwal->day_of_week] ?? '?' }},
                                {{ \Carbon\Carbon::parse($jadwal->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($jadwal->end_time)->format('H:i') }}
                                &nbsp;·&nbsp;
                                {{ $jadwal->room->name ?? '—' }}
                                @if($jadwal->room)
                                    <span class="font-mono">({{ $jadwal->room->code }})</span>
                                @endif
                            @else
                                &nbsp;·&nbsp; <span class="italic text-gray-400">Jadwal belum diatur</span>
                            @endif
                        </div>
                        <div>
                            Mulai: {{ $enrollment->effective_date ? \Carbon\Carbon::parse($enrollment->effective_date)->format('d M Y') : '—' }}
                            &nbsp;·&nbsp;
                            {{ $enrollment->package->duration_min ?? '?' }} menit/sesi
                            &nbsp;·&nbsp;
                            {{ $enrollment->package->formatted_price ?? '—' }}/bln
                        </div>
                    </div>
                </div>

                {{-- Status badge --}}
                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold flex-shrink-0 mt-1"
                      style="background:rgba(52,211,153,0.12);color:#34D399">
                    Berjalan
                </span>

                {{-- Aksi (hanya Owner/Admin) --}}
                @if(auth()->user()->hasAnyRole(['Owner', 'Admin']))
                    <div class="flex items-center gap-2 flex-shrink-0 mt-0.5">
                        {{-- Tombol "Jadikan Utama" hanya muncul jika bukan kelas utama --}}
                        @if(!$enrollment->is_primary)
                            <form method="POST"
                                  action="{{ route('students.enrollments.set-primary', [$student, $enrollment]) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors"
                                        style="background:rgba(212,168,83,0.12);color:#D4A853;border:1px solid rgba(212,168,83,0.25)">
                                    Jadikan Utama
                                </button>
                            </form>
                        @endif
                        {{-- Tombol hentikan kelas --}}
                        <form method="POST"
                              action="{{ route('students.enrollments.destroy', [$student, $enrollment]) }}"
                              onsubmit="return confirm('Hentikan kelas {{ addslashes($enrollment->package->code ?? '') }}? Jadwal aktif akan dinonaktifkan.')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors"
                                    style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
                                Hentikan
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <div class="px-5 py-8 text-center text-sm text-gray-400">
                Belum ada kelas berjalan.<br>
                <span class="text-xs">Klik "+ Tambah Kelas" untuk mendaftarkan kelas baru.</span>
            </div>
        @endforelse
    </div>

    {{-- ===== RIWAYAT KELAS ===== --}}
    @if($historyEnrollments->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100">
                <div class="text-[10px] uppercase tracking-widest font-semibold" style="color:#D4A853">
                    Riwayat Kelas
                </div>
            </div>
            @foreach($historyEnrollments as $enrollment)
                <div class="px-5 py-3.5 flex items-center gap-4 border-b border-gray-100 last:border-0 opacity-60">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base flex-shrink-0"
                         style="background:rgba(212,168,83,0.07)">
                        🎵
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-700">
                            {{ $enrollment->package->instrument->name ?? '—' }}
                            &nbsp;·&nbsp;
                            <span class="font-mono">{{ $enrollment->package->code ?? '—' }}</span>
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            Guru: {{ $enrollment->teacher->name ?? '—' }}
                            &nbsp;·&nbsp;
                            {{ $enrollment->effective_date ? \Carbon\Carbon::parse($enrollment->effective_date)->format('M Y') : '—' }}
                            –
                            {{ $enrollment->end_date ? \Carbon\Carbon::parse($enrollment->end_date)->format('M Y') : '—' }}
                        </div>
                    </div>
                    {{-- Badge status berdasarkan nilai INACTIVE/COMPLETED --}}
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold flex-shrink-0"
                          style="{{ $enrollment->status === 'COMPLETED'
                              ? 'background:rgba(96,165,250,0.12);color:#60A5FA'
                              : 'background:rgba(139,146,168,0.12);color:#8B92A8' }}">
                        {{ $enrollment->status === 'COMPLETED' ? 'Selesai' : 'Dihentikan' }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif

</div>

{{-- ===== MODAL: TAMBAH KELAS ===== --}}
{{-- Modal ditutup dengan klik backdrop atau tombol batal/× --}}
<div id="modal-tambah-kelas"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,0.55)"
     onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[80vh] flex flex-col"
         onclick="event.stopPropagation()">

        {{-- Header modal — fixed, tidak ikut scroll --}}
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <div>
                <h4 class="font-semibold text-gray-800 text-sm">Tambah Kelas</h4>
                <p class="text-xs text-gray-400 mt-0.5">{{ $student->full_name }}</p>
            </div>
            <button type="button"
                    onclick="document.getElementById('modal-tambah-kelas').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none w-8 h-8 flex items-center justify-center rounded">&times;</button>
        </div>

        {{-- Body scrollable --}}
        {{-- packageMap: mapping package_id → instrument_id untuk filter guru --}}
        <form method="POST" action="{{ route('students.enrollments.store', $student) }}" class="flex flex-col flex-1 min-h-0"
              x-data="{
                packageMap: {{ json_encode($allPackages->mapWithKeys(fn($p) => [$p->id => $p->instrument_id])) }},
                teachers: [],
                loadingTeachers: false,
                onPackageChange(pkgId) {
                    const instrId = this.packageMap[pkgId];
                    if (!instrId) { this.teachers = []; return; }
                    this.loadingTeachers = true;
                    fetch('{{ route('api.teachers-by-instrument', '') }}/' + instrId)
                        .then(r => r.json())
                        .then(data => { this.teachers = data; this.loadingTeachers = false; })
                        .catch(() => { this.teachers = []; this.loadingTeachers = false; });
                }
              }">
            @csrf
            <div class="px-5 py-4 grid grid-cols-2 gap-x-4 gap-y-3 overflow-y-auto flex-1">

                {{-- Pilih paket --}}
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">
                        Paket <span class="text-red-400">*</span>
                    </label>
                    <select name="package_id" required
                            @change="onPackageChange($event.target.value)"
                            class="block w-full rounded-lg text-sm px-3 py-2 border border-gray-200">
                        <option value="">— Pilih Paket —</option>
                        @foreach($allPackages as $pkg)
                            <option value="{{ $pkg->id }}">
                                [{{ $pkg->code }}] {{ $pkg->instrument->name ?? '-' }}
                                · {{ $pkg->formatted_price }}/bln
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Pilih guru — difilter sesuai instrumen paket yang dipilih --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">
                        Guru <span class="text-red-400">*</span>
                    </label>
                    <select name="teacher_id" required class="block w-full rounded-lg text-sm px-3 py-2 border border-gray-200"
                            :disabled="!teachers.length">
                        <option value="">
                            <span x-show="!teachers.length">— Pilih paket dulu —</span>
                            <span x-show="teachers.length">— Pilih Guru —</span>
                        </option>
                        <template x-for="t in teachers" :key="t.id">
                            <option :value="t.id" x-text="t.name"></option>
                        </template>
                    </select>
                    <p x-show="loadingTeachers" class="text-xs text-gray-400 mt-1">Memuat guru…</p>
                </div>

                {{-- Pilih ruangan --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">
                        Ruangan <span class="text-red-400">*</span>
                    </label>
                    <select name="room_id" required class="block w-full rounded-lg text-sm px-3 py-2 border border-gray-200">
                        <option value="">— Pilih —</option>
                        @foreach($allRooms as $room)
                            <option value="{{ $room->id }}">[{{ $room->code }}] {{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Hari jadwal --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">
                        Hari <span class="text-red-400">*</span>
                    </label>
                    <select name="day_of_week" required class="block w-full rounded-lg text-sm px-3 py-2 border border-gray-200">
                        @foreach(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $i => $hari)
                            <option value="{{ $i }}" {{ $i === 1 ? 'selected' : '' }}>{{ $hari }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Jam mulai --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">
                        Jam Mulai <span class="text-red-400">*</span>
                    </label>
                    <input type="time" name="start_time" value="15:00" required
                           class="block w-full rounded-lg text-sm px-3 py-2 border border-gray-200">
                </div>

                {{-- Berlaku mulai --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">
                        Berlaku Mulai <span class="text-red-400">*</span>
                    </label>
                    <input type="date" name="effective_date"
                           value="{{ now()->addDay()->format('Y-m-d') }}" required
                           class="block w-full rounded-lg text-sm px-3 py-2 border border-gray-200">
                </div>

                {{-- Jadikan utama (checkbox) --}}
                <div class="col-span-2 flex items-center gap-2 pt-1">
                    <input type="checkbox" name="jadikan_utama" value="1" id="modal-jadikan-utama"
                           class="rounded border-gray-300">
                    <label for="modal-jadikan-utama" class="text-sm text-gray-600 cursor-pointer">
                        Jadikan kelas utama
                        <span class="text-xs text-gray-400">(kelas referensi SPP &amp; honor)</span>
                    </label>
                </div>

            </div>

            {{-- Footer modal — fixed, tidak ikut scroll --}}
            <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2 flex-shrink-0">
                <button type="button"
                        onclick="document.getElementById('modal-tambah-kelas').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-gray-600 rounded-lg transition-colors"
                        style="border:1px solid rgba(212,168,83,0.2)">
                    Batal
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors"
                        style="background:rgba(212,168,83,0.9);color:#1A1000">
                    Simpan &amp; Buat Jadwal
                </button>
            </div>
        </form>
    </div>
</div>
