<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-xs text-gray-500 mb-0.5">
                    <a href="{{ route('students.index') }}" class="hover:underline">Daftar Murid</a>
                    <span class="mx-1">→</span>
                    {{ $student->full_name }}
                </div>
                <h2 class="font-semibold text-xl text-gray-800">Detail Murid</h2>
            </div>
            <a href="{{ route('students.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    @php
        // Konfigurasi warna per status — inline style agar tidak terpengaruh .dark-content
        $statusCfg = [
            'Calon'             => ['bg' => 'rgba(139,146,168,0.12)', 'color' => '#8B92A8', 'dot' => '#8B92A8'],
            'Trial'             => ['bg' => 'rgba(167,139,250,0.12)', 'color' => '#A78BFA', 'dot' => '#A78BFA'],
            'Aktif'             => ['bg' => 'rgba(52,211,153,0.12)',  'color' => '#34D399', 'dot' => '#34D399'],
            'Cuti'              => ['bg' => 'rgba(251,191,36,0.12)',  'color' => '#FBBF24', 'dot' => '#FBBF24'],
            'Selesai'           => ['bg' => 'rgba(96,165,250,0.12)', 'color' => '#60A5FA',  'dot' => '#60A5FA'],
            'Mengundurkan Diri' => ['bg' => 'rgba(248,113,113,0.12)','color' => '#F87171', 'dot' => '#F87171'],
        ];
        $sessionStatusCfg = [
            'SCHEDULED'       => ['bg' => 'rgba(139,146,168,0.12)', 'color' => '#8B92A8'],
            'HADIR'           => ['bg' => 'rgba(52,211,153,0.12)',  'color' => '#34D399'],
            'HADIR_TERLAMBAT' => ['bg' => 'rgba(251,191,36,0.12)', 'color' => '#FBBF24'],
            'IZIN_RESCHEDULE' => ['bg' => 'rgba(96,165,250,0.12)', 'color' => '#60A5FA'],
            'IZIN_VIDEO'      => ['bg' => 'rgba(129,140,248,0.12)','color' => '#818CF8'],
            'HANGUS'          => ['bg' => 'rgba(248,113,113,0.12)','color' => '#F87171'],
            'LIBUR'           => ['bg' => 'rgba(167,139,250,0.12)','color' => '#A78BFA'],
            'DIGANTI'         => ['bg' => 'rgba(251,146,60,0.12)', 'color' => '#FB923C'],
        ];
        $invStatusCfg = [
            'UNPAID'  => ['bg' => 'rgba(248,113,113,0.12)', 'color' => '#F87171'],
            'PARTIAL' => ['bg' => 'rgba(251,191,36,0.12)',  'color' => '#FBBF24'],
            'PAID'    => ['bg' => 'rgba(52,211,153,0.12)',  'color' => '#34D399'],
        ];
        $cfg      = $statusCfg[$student->status] ?? $statusCfg['Calon'];
        $hariMap  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        // Cuti selesai jika tidak ada cuti_until, atau tanggal hari ini >= cuti_until
        $cutiSelesai = !$student->cuti_until || now()->toDateString() >= $student->cuti_until->format('Y-m-d');
        $isKidsClass = $student->package
            && in_array($student->package->class_type, ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE']);
        $activeEnrollment = $student->enrollments->firstWhere('status', 'ACTIVE');
        $studentInstrument = $activeEnrollment?->package?->instrument?->name;
    @endphp

    <div class="py-6 px-4 lg:px-8 max-w-4xl mx-auto space-y-5">

        {{-- Flash messages --}}
        @if(session('success'))
        <div class="p-3 rounded-lg text-sm fade-in-up"
             style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="p-3 rounded-lg text-sm fade-in-up"
             style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
            {{ session('error') }}
        </div>
        @endif
        @if(session('warning'))
        <div class="p-3 rounded-lg text-sm fade-in-up"
             style="background:rgba(251,191,36,0.1);color:#F59E0B;border:1px solid rgba(251,191,36,0.2)">
            ⚠️ {{ session('warning') }}
        </div>
        @endif
        @if($errors->any())
        <div class="p-3 rounded-lg text-sm fade-in-up"
             style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
            <div class="font-semibold mb-1">Form aksi gagal divalidasi:</div>
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- ===== HEADER CARD ===== --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 fade-in-up" style="animation-delay:0ms">
            <div class="flex justify-between items-start gap-4">

                {{-- Avatar + Info --}}
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl shrink-0"
                         style="background:{{ $cfg['bg'] }}">
                        {{ $student->gender === 'P' ? '👩' : '👦' }}
                    </div>
                    <div>
                        <div class="font-mono text-xs text-gray-500 mb-0.5">{{ $student->student_code }}</div>
                        <div class="text-2xl font-bold text-gray-800 leading-tight">{{ $student->full_name }}</div>
                        <div class="text-sm text-gray-500 mt-0.5">
                            @if($student->nickname)"{{ $student->nickname }}" · @endif
                            {{ $student->gender == 'L' ? 'Laki-laki' : 'Perempuan' }}
                            @if($student->age) · {{ $student->age }} tahun @endif
                        </div>
                        <div class="mt-2.5">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-semibold"
                                  style="background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }}">
                                <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $cfg['dot'] }}"></span>
                                {{ $student->status }}
                            </span>
                            {{-- Sub-teks periode cuti, hanya tampil jika status Cuti dan cuti_until terisi --}}
                            @if($student->status === 'Cuti' && $student->cuti_until)
                            <div class="text-xs mt-1" style="color:#FBBF24">
                                Cuti s/d: {{ $student->cuti_until->format('d M Y') }}
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Edit button --}}
                <a href="{{ route('students.edit', $student->id) }}"
                   class="px-4 py-2 rounded-lg text-sm font-semibold shrink-0 transition-colors"
                   style="background:rgba(212,168,83,0.15);color:#D4A853;border:1px solid rgba(212,168,83,0.3)">
                    Edit Data
                </a>
            </div>
        </div>

        {{-- ===== LIFECYCLE PANEL ===== --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 fade-in-up"
             style="animation-delay:80ms"
             x-data="{ openForm: null }">

            <div class="text-xs text-gray-500 uppercase tracking-widest font-semibold mb-3">Aksi Lifecycle</div>

            {{-- Tombol aksi per status --}}
            <div class="flex flex-wrap gap-2">

                @if($student->status === 'Calon')
                <button type="button" @click="openForm = openForm === 'trial' ? null : 'trial'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        style="background:rgba(167,139,250,0.15);color:#A78BFA;border:1px solid rgba(167,139,250,0.3)">
                    🎯 Mulai Trial
                </button>
                <button type="button" @click="openForm = openForm === 'skip' ? null : 'skip'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        style="background:rgba(52,211,153,0.15);color:#34D399;border:1px solid rgba(52,211,153,0.3)">
                    ⚡ Skip Trial → Aktif
                </button>
                @endif

                @if($student->status === 'Trial')
                <button type="button" @click="openForm = openForm === 'convert' ? null : 'convert'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        style="background:rgba(52,211,153,0.15);color:#34D399;border:1px solid rgba(52,211,153,0.3)">
                    ✅ Konversi → Aktif
                </button>
                @endif

                @if($student->status === 'Aktif')
                <button type="button" @click="openForm = openForm === 'cuti' ? null : 'cuti'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        style="background:rgba(251,191,36,0.15);color:#FBBF24;border:1px solid rgba(251,191,36,0.3)">
                    ☕ Ajukan Cuti
                </button>
                @if($isKidsClass)
                <button type="button" @click="openForm = openForm === 'complete' ? null : 'complete'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        style="background:rgba(96,165,250,0.15);color:#60A5FA;border:1px solid rgba(96,165,250,0.3)">
                    🎓 Tandai Selesai (Kids)
                </button>
                @endif
                @endif

                @if($student->status === 'Cuti')
                {{-- Tombol akhiri cuti: aktif jika periode cuti sudah selesai, disabled jika belum --}}
                @if($cutiSelesai)
                <form method="POST" action="{{ route('students.return-from-cuti', $student->id) }}"
                      onsubmit="return confirm('Akhiri cuti dan kembalikan ke Aktif?')" class="inline">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                            style="background:rgba(52,211,153,0.15);color:#34D399;border:1px solid rgba(52,211,153,0.3)">
                        ✅ Akhiri Cuti → Aktif
                    </button>
                </form>
                @else
                <button disabled
                        title="Cuti berlaku hingga {{ $student->cuti_until->format('d M Y') }}"
                        class="px-4 py-2 rounded-lg text-sm font-semibold cursor-not-allowed"
                        style="background:rgba(52,211,153,0.05);color:rgba(52,211,153,0.35);border:1px solid rgba(52,211,153,0.1)">
                    ✅ Akhiri Cuti → Aktif
                </button>
                @endif
                <button type="button" @click="openForm = openForm === 'cuti' ? null : 'cuti'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        style="background:rgba(251,191,36,0.15);color:#FBBF24;border:1px solid rgba(251,191,36,0.3)">
                    ↩ Perpanjang Cuti
                </button>
                @endif

                @if(in_array($student->status, ['Selesai', 'Mengundurkan Diri']))
                <button type="button" @click="openForm = openForm === 'reactivate' ? null : 'reactivate'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        style="background:rgba(52,211,153,0.15);color:#34D399;border:1px solid rgba(52,211,153,0.3)">
                    🔄 {{ $student->status === 'Selesai' ? 'Re-enroll Privat' : 'Re-aktivasi' }}
                </button>
                @endif

                @if(in_array($student->status, ['Calon', 'Trial', 'Aktif', 'Cuti']))
                <button type="button" @click="openForm = openForm === 'withdraw' ? null : 'withdraw'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors"
                        style="background:rgba(248,113,113,0.15);color:#F87171;border:1px solid rgba(248,113,113,0.3)">
                    🚪 Tandai Mundur
                </button>
                @endif

                @if(in_array($student->status, ['Mengundurkan Diri', 'Selesai']))
                <span class="text-xs text-gray-400 self-center">Status terminal — gunakan Re-aktivasi di atas.</span>
                @endif
            </div>

            {{-- ===== FORM PANELS ===== --}}

            {{-- Trial --}}
            <div x-show="openForm === 'trial'" x-cloak
                 class="mt-4 rounded-xl p-4"
                 style="background:rgba(167,139,250,0.08);border:1px solid rgba(167,139,250,0.2)">
                <form method="POST" action="{{ route('students.start-trial', $student->id) }}">
                    @csrf
                    <div class="text-sm font-semibold mb-3" style="color:#A78BFA">Jadwalkan Trial (30 menit)</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Tanggal & Jam <span class="text-red-400">*</span></label>
                            <input type="datetime-local" name="trial_date" required
                                   min="{{ now()->addHour()->format('Y-m-d\TH:i') }}"
                                   class="block w-full rounded-lg text-sm px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Paket Diminati</label>
                            <select name="package_id" class="block w-full rounded-lg text-sm px-3 py-2">
                                <option value="">— Pilih —</option>
                                @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}" {{ $student->package?->id == $pkg->id ? 'selected' : '' }}>
                                    [{{ $pkg->code }}] {{ $pkg->instrument->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Guru Trial <span class="text-red-400">*</span></label>
                            <select name="assigned_teacher_id" required class="block w-full rounded-lg text-sm px-3 py-2">
                                <option value="">— Pilih —</option>
                                @foreach($teachers as $t)
                                <option value="{{ $t->id }}" {{ $student->assignedTeacher?->id == $t->id ? 'selected' : '' }}>
                                    [{{ $t->code }}] {{ $t->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Ruangan</label>
                            <select name="assigned_room_id" class="block w-full rounded-lg text-sm px-3 py-2">
                                <option value="">— Pilih —</option>
                                @foreach($rooms as $r)
                                <option value="{{ $r->id }}" {{ $student->primaryEnrollment?->schedules->first()?->room_id == $r->id ? 'selected' : '' }}>
                                    [{{ $r->code }}] {{ $r->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Catatan</label>
                            <textarea name="notes" rows="2" maxlength="500" class="block w-full rounded-lg text-sm px-3 py-2"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="mt-3 px-4 py-2 rounded-lg text-sm font-semibold"
                            style="background:rgba(167,139,250,0.2);color:#A78BFA">
                        Simpan Jadwal Trial
                    </button>
                </form>
            </div>

            {{-- Skip Trial --}}
            <div x-show="openForm === 'skip'" x-cloak
                 x-data="lifecycleTeacherFilter()"
                 class="mt-4 rounded-xl p-4"
                 style="background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.2)">
                <form method="POST" action="{{ route('students.skip-trial', $student->id) }}">
                    @csrf
                    <div class="text-sm font-semibold mb-1" style="color:#34D399">Skip Trial → Langsung Aktif</div>
                    <div class="text-xs text-gray-500 mb-3">Tagihan REG + SPP otomatis di-flag setelah konfirmasi.</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Kode Alasan <span class="text-red-400">*</span></label>
                            <select name="reason_code" required class="block w-full rounded-lg text-sm px-3 py-2">
                                <option value="">— Pilih —</option>
                                <option value="walk_in">Walk-in (datang langsung confident)</option>
                                <option value="migrasi">Migrasi data sistem lama</option>
                                <option value="reaktivasi">Reaktivasi murid lama</option>
                                <option value="lulus_kids">Lulus Kids Class lanjut privat</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Penjelasan Detail <span class="text-red-400">*</span></label>
                            <textarea name="reason" required rows="2" maxlength="500"
                                      class="block w-full rounded-lg text-sm px-3 py-2"
                                      placeholder="Konteks tambahan untuk audit trail."></textarea>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Paket <span class="text-red-400">*</span></label>
                            <select name="package_id" required class="block w-full rounded-lg text-sm px-3 py-2"
                                    @change="filterTeachers($event.target.selectedOptions[0]?.dataset?.instrumentId || '', $event.target.selectedOptions[0]?.dataset?.classType || '')">
                                <option value="" data-instrument-id="" data-class-type="">— Pilih —</option>
                                @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}"
                                        data-instrument-id="{{ $pkg->instrument->id }}"
                                        data-class-type="{{ $pkg->class_type }}">
                                    [{{ $pkg->code }}] {{ $pkg->instrument->name }} ({{ $pkg->formatted_price }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Guru Utama <span class="text-red-400">*</span></label>
                            <select name="assigned_teacher_id" required
                                    class="block w-full rounded-lg text-sm px-3 py-2"
                                    :disabled="loadingTeachers">
                                <option value="" x-text="loadingTeachers ? 'Memuat guru...' : (teachers.length ? '— Pilih Guru —' : '— Pilih paket dulu —')"></option>
                                <template x-for="t in teachers" :key="t.id">
                                    <option :value="t.id" x-text="'[' + t.code + '] ' + t.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Ruangan</label>
                            <select name="assigned_room_id" class="block w-full rounded-lg text-sm px-3 py-2">
                                <option value="">— Pilih —</option>
                                @foreach($rooms as $r)
                                <option value="{{ $r->id }}">[{{ $r->code }}] {{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Metode pembayaran: hanya muncul untuk KIDS_CLASS_BUNDLE --}}
                        <div x-show="kidsBundle" class="md:col-span-2 p-3 rounded-lg border border-blue-200 bg-blue-50">
                            <div class="text-xs font-semibold text-blue-700 mb-2">Metode Pembayaran Kids Class Bundle</div>
                            <div class="flex gap-6">
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="payment_mode" value="FULL" checked> Lunas sekali bayar
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="payment_mode" value="INSTALLMENT"> Cicilan 3 Termin (bulan ke-1, 2, 4)
                                </label>
                            </div>
                        </div>
                        {{-- Hidden default FULL untuk non-bundle --}}
                        <template x-if="!kidsBundle"><input type="hidden" name="payment_mode" value="FULL"></template>
                    </div>
                    <button type="submit" class="mt-3 px-4 py-2 rounded-lg text-sm font-semibold"
                            style="background:rgba(52,211,153,0.2);color:#34D399">
                        Konfirmasi Skip Trial
                    </button>
                </form>
            </div>

            {{-- Konversi Aktif --}}
            <div x-show="openForm === 'convert'" x-cloak
                 x-data="lifecycleTeacherFilter()"
                 class="mt-4 rounded-xl p-4"
                 style="background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.2)">
                <form method="POST" action="{{ route('students.convert-active', $student->id) }}">
                    @csrf
                    <div class="text-sm font-semibold mb-1" style="color:#34D399">Konversi Trial → Aktif</div>
                    <div class="text-xs text-gray-500 mb-3">Tagihan REG (Rp 250.000) + SPP bulan pertama akan di-flag.</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Paket <span class="text-red-400">*</span></label>
                            <select name="package_id" required
                                    class="block w-full rounded-lg text-sm px-3 py-2"
                                    @change="filterTeachers($event.target.selectedOptions[0]?.dataset?.instrumentId || '', $event.target.selectedOptions[0]?.dataset?.classType || '')">
                                <option value="" data-instrument-id="" data-class-type="">— Pilih —</option>
                                @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}"
                                        data-instrument-id="{{ $pkg->instrument->id }}"
                                        data-class-type="{{ $pkg->class_type }}"
                                        {{ $student->package?->id == $pkg->id ? 'selected' : '' }}>
                                    [{{ $pkg->code }}] {{ $pkg->instrument->name }} ({{ $pkg->formatted_price }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Guru Utama <span class="text-red-400">*</span></label>
                            <select name="assigned_teacher_id" required
                                    class="block w-full rounded-lg text-sm px-3 py-2"
                                    :disabled="loadingTeachers">
                                <option value="" x-text="loadingTeachers ? 'Memuat guru...' : (teachers.length ? '— Pilih Guru —' : '— Pilih paket dulu —')"></option>
                                <template x-for="t in teachers" :key="t.id">
                                    <option :value="t.id" x-text="'[' + t.code + '] ' + t.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Ruangan</label>
                            <select name="assigned_room_id" class="block w-full rounded-lg text-sm px-3 py-2">
                                <option value="">— Pilih —</option>
                                @foreach($rooms as $r)
                                <option value="{{ $r->id }}" {{ $student->primaryEnrollment?->schedules->first()?->room_id == $r->id ? 'selected' : '' }}>
                                    [{{ $r->code }}] {{ $r->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Catatan</label>
                            <textarea name="notes" rows="2" maxlength="500" class="block w-full rounded-lg text-sm px-3 py-2"></textarea>
                        </div>
                        {{-- Metode pembayaran: hanya muncul untuk KIDS_CLASS_BUNDLE --}}
                        <div x-show="kidsBundle" class="md:col-span-2 p-3 rounded-lg border border-blue-200 bg-blue-50">
                            <div class="text-xs font-semibold text-blue-700 mb-2">Metode Pembayaran Kids Class Bundle</div>
                            <div class="flex gap-6">
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="payment_mode" value="FULL" checked> Lunas sekali bayar
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="payment_mode" value="INSTALLMENT"> Cicilan 3 Termin (bulan ke-1, 2, 4)
                                </label>
                            </div>
                        </div>
                        <template x-if="!kidsBundle"><input type="hidden" name="payment_mode" value="FULL"></template>
                    </div>
                    <button type="submit" class="mt-3 px-4 py-2 rounded-lg text-sm font-semibold"
                            style="background:rgba(52,211,153,0.2);color:#34D399">
                        Konfirmasi Konversi Aktif
                    </button>
                </form>
            </div>

            {{-- Cuti --}}
            <div x-show="openForm === 'cuti'" x-cloak
                 class="mt-4 rounded-xl p-4"
                 style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2)">
                <form method="POST" action="{{ route('students.start-cuti', $student->id) }}">
                    @csrf
                    <div class="text-sm font-semibold mb-1" style="color:#FBBF24">
                        {{ $student->status === 'Cuti' ? 'Perpanjang Cuti' : 'Ajukan Cuti' }}
                    </div>
                    <div class="text-xs text-gray-500 mb-3">Biaya Rp 100.000/pengajuan. Maks 1 bulan + perpanjang 1x.</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {{-- cuti_from hanya tampil saat ajukan baru (status Aktif); perpanjang tidak perlu --}}
                        @if($student->status !== 'Cuti')
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Mulai Cuti <span class="text-red-400">*</span></label>
                            <input type="date" name="cuti_from" required min="{{ now()->toDateString() }}"
                                   class="block w-full rounded-lg text-sm px-3 py-2">
                        </div>
                        @endif
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Sampai <span class="text-red-400">*</span></label>
                            <input type="date" name="cuti_until" required class="block w-full rounded-lg text-sm px-3 py-2">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Alasan <span class="text-red-400">*</span></label>
                            <textarea name="reason" required rows="2" maxlength="500"
                                      class="block w-full rounded-lg text-sm px-3 py-2"
                                      placeholder="Mis: UAS sekolah, perjalanan keluarga, dll."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="mt-3 px-4 py-2 rounded-lg text-sm font-semibold"
                            style="background:rgba(251,191,36,0.2);color:#FBBF24">
                        Simpan Pengajuan Cuti
                    </button>
                </form>
            </div>

            {{-- Selesai Kids --}}
            <div x-show="openForm === 'complete'" x-cloak
                 class="mt-4 rounded-xl p-4"
                 style="background:rgba(96,165,250,0.08);border:1px solid rgba(96,165,250,0.2)">
                <form method="POST" action="{{ route('students.complete', $student->id) }}">
                    @csrf
                    <div class="text-sm font-semibold mb-1" style="color:#60A5FA">Tandai Selesai (Lulus Kids Class)</div>
                    <div class="text-xs text-gray-500 mb-3">Murid dapat re-enroll privat tanpa bayar registrasi ulang (BR-10.7).</div>
                    <textarea name="notes" rows="2" maxlength="500"
                              class="block w-full rounded-lg text-sm px-3 py-2"
                              placeholder="Catatan kelulusan (opsional)"></textarea>
                    <button type="submit" onclick="return confirm('Tandai murid Selesai?')"
                            class="mt-3 px-4 py-2 rounded-lg text-sm font-semibold"
                            style="background:rgba(96,165,250,0.2);color:#60A5FA">
                        Konfirmasi Selesai
                    </button>
                </form>
            </div>

            {{-- Re-aktivasi --}}
            <div x-show="openForm === 'reactivate'" x-cloak
                 x-data="lifecycleTeacherFilter()"
                 class="mt-4 rounded-xl p-4"
                 style="background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.2)">
                <form method="POST" action="{{ route('students.reactivate', $student->id) }}">
                    @csrf
                    <div class="text-sm font-semibold mb-1" style="color:#34D399">
                        {{ $student->status === 'Selesai' ? 'Re-enroll Privat (tanpa registrasi ulang)' : 'Re-aktivasi (bayar registrasi Rp 250.000)' }}
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Paket <span class="text-red-400">*</span></label>
                            <select name="package_id" required
                                    class="block w-full rounded-lg text-sm px-3 py-2"
                                    @change="filterTeachers($event.target.selectedOptions[0]?.dataset?.instrumentId || '', $event.target.selectedOptions[0]?.dataset?.classType || '')">
                                <option value="" data-instrument-id="" data-class-type="">— Pilih —</option>
                                @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}"
                                        data-instrument-id="{{ $pkg->instrument->id }}"
                                        data-class-type="{{ $pkg->class_type }}">
                                    [{{ $pkg->code }}] {{ $pkg->instrument->name }} ({{ $pkg->formatted_price }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Guru Utama <span class="text-red-400">*</span></label>
                            <select name="assigned_teacher_id" required
                                    class="block w-full rounded-lg text-sm px-3 py-2"
                                    :disabled="loadingTeachers">
                                <option value="" x-text="loadingTeachers ? 'Memuat guru...' : (teachers.length ? '— Pilih Guru —' : '— Pilih paket dulu —')"></option>
                                <template x-for="t in teachers" :key="t.id">
                                    <option :value="t.id" x-text="'[' + t.code + '] ' + t.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Ruangan</label>
                            <select name="assigned_room_id" class="block w-full rounded-lg text-sm px-3 py-2">
                                <option value="">— Pilih —</option>
                                @foreach($rooms as $r)
                                <option value="{{ $r->id }}">[{{ $r->code }}] {{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Catatan</label>
                            <textarea name="notes" rows="2" maxlength="500" class="block w-full rounded-lg text-sm px-3 py-2"></textarea>
                        </div>
                        {{-- Metode pembayaran: hanya muncul untuk KIDS_CLASS_BUNDLE --}}
                        <div x-show="kidsBundle" class="md:col-span-2 p-3 rounded-lg border border-blue-200 bg-blue-50">
                            <div class="text-xs font-semibold text-blue-700 mb-2">Metode Pembayaran Kids Class Bundle</div>
                            <div class="flex gap-6">
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="payment_mode" value="FULL" checked> Lunas sekali bayar
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="payment_mode" value="INSTALLMENT"> Cicilan 3 Termin (bulan ke-1, 2, 4)
                                </label>
                            </div>
                        </div>
                        <template x-if="!kidsBundle"><input type="hidden" name="payment_mode" value="FULL"></template>
                    </div>
                    <button type="submit" class="mt-3 px-4 py-2 rounded-lg text-sm font-semibold"
                            style="background:rgba(52,211,153,0.2);color:#34D399">
                        Konfirmasi Re-aktivasi
                    </button>
                </form>
            </div>

            {{-- Mundur --}}
            <div x-show="openForm === 'withdraw'" x-cloak
                 class="mt-4 rounded-xl p-4"
                 style="background:rgba(248,113,113,0.08);border:1px solid rgba(248,113,113,0.2)">
                <form method="POST" action="{{ route('students.withdraw', $student->id) }}"
                      onsubmit="return confirm('Tandai murid Mengundurkan Diri? Bisa di-rollback via Re-aktivasi.')">
                    @csrf
                    <div class="text-sm font-semibold mb-1" style="color:#F87171">Tandai Murid Mundur</div>
                    <div class="text-xs text-gray-500 mb-3">Murid bisa diaktifkan kembali via Re-aktivasi (wajib bayar registrasi Rp 250.000).</div>
                    <label class="block text-xs text-gray-500 mb-1">Alasan <span class="text-red-400">*</span></label>
                    <textarea name="reason" required rows="2" maxlength="500"
                              class="block w-full rounded-lg text-sm px-3 py-2"
                              placeholder="Mis: pindah kota, tunggakan >1 bulan, tidak melanjutkan setelah trial"></textarea>
                    <button type="submit" class="mt-3 px-4 py-2 rounded-lg text-sm font-semibold"
                            style="background:rgba(248,113,113,0.2);color:#F87171">
                        Konfirmasi Mundur
                    </button>
                </form>
            </div>
        </div>

        {{-- ===== TAB NAVIGASI ===== --}}
        <div x-data="{ activeTab: 'info', openSchedule: null }" class="space-y-0">

            {{-- Tab pills --}}
            <div class="flex gap-1 p-1 bg-white rounded-xl border border-gray-100 shadow-sm mb-5 fade-in-up"
                 style="animation-delay:160ms">
                @foreach([
                    ['info',    '📋 Informasi'],
                    ['kelas',   '🎵 Kelas'],
                    ['jadwal',  '📅 Jadwal & Sesi'],
                    ['tagihan', '💳 Tagihan'],
                    ['history', '📜 Riwayat'],
                ] as [$tab, $label])
                <button @click="activeTab = '{{ $tab }}'"
                        :style="activeTab === '{{ $tab }}'
                            ? 'background:rgba(212,168,83,0.15);color:#D4A853'
                            : 'background:transparent;color:#6B7494'"
                        class="flex-1 py-2 px-1 rounded-lg text-xs font-medium transition-all duration-150 text-center">
                    {{ $label }}
                </button>
                @endforeach
            </div>

            {{-- ===== TAB: INFORMASI ===== --}}
            <div x-show="activeTab === 'info'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    {{-- Identitas & Kontak --}}
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                        <div class="text-[10px] uppercase tracking-widest font-semibold mb-4"
                             style="color:#D4A853">Identitas & Kontak</div>
                        <div class="space-y-3">
                            @foreach([
                                ['Jenis Kelamin', $student->gender == 'L' ? 'Laki-laki' : 'Perempuan'],
                                ['Tanggal Lahir',  ($student->birth_date?->format('d M Y') ?? '—') . ($student->age ? ' (' . $student->age . ' thn)' : '')],
                                ['No. HP',  $student->phone ?? '—'],
                                ['Email',   $student->email ?? '—'],
                                ['Alamat',  $student->address ?? '—'],
                            ] as [$label, $value])
                            <div class="flex gap-3">
                                <div class="text-xs text-gray-500 w-28 shrink-0 pt-0.5">{{ $label }}</div>
                                <div class="text-sm text-gray-800 flex-1">{{ $value }}</div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Orang Tua / Wali --}}
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                        <div class="text-[10px] uppercase tracking-widest font-semibold mb-4"
                             style="color:#D4A853">Orang Tua / Wali</div>
                        <div class="space-y-3">
                            @foreach([
                                ['Nama',        $student->parent_name ?? '—'],
                                ['Hubungan',    $student->parent_relationship ?? '—'],
                                ['No. HP',      $student->parent_phone ?? '—'],
                                ['Email',       $student->parent_email ?? '—'],
                            ] as [$label, $value])
                            <div class="flex gap-3">
                                <div class="text-xs text-gray-500 w-28 shrink-0 pt-0.5">{{ $label }}</div>
                                <div class="text-sm text-gray-800 flex-1">{{ $value }}</div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Status Belajar --}}
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                        <div class="text-[10px] uppercase tracking-widest font-semibold mb-4"
                             style="color:#D4A853">Status Belajar</div>
                        <div class="space-y-3">
                            <div class="flex gap-3">
                                <div class="text-xs text-gray-500 w-28 shrink-0 pt-0.5">Paket</div>
                                <div class="text-sm text-gray-800 flex-1">
                                    @if($student->package)
                                    <span class="font-mono">{{ $student->package->code }}</span>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ $student->package->class_type }}
                                        @if($student->package->grade) · {{ $student->package->grade }} @endif
                                        · {{ $student->package->duration_min }} menit
                                        · {{ $student->package->formatted_price }}/bln
                                    </div>
                                    @else <span class="text-gray-400">—</span>@endif
                                </div>
                            </div>
                            @php
                                // Ambil jadwal aktif dari primary enrollment
                                $infoSch = $student->primaryEnrollment?->schedules()->where('is_active', true)->first();
                                $jadwalText = $infoSch
                                    ? (($hariMap[$infoSch->day_of_week] ?? '—') . ', ' . \Carbon\Carbon::parse($infoSch->start_time)->format('H:i'))
                                    : '—';
                            @endphp
                            @foreach([
                                ['Instrumen',   $student->package?->instrument?->name ?? '—'],
                                ['Guru Utama',  ($student->assignedTeacher?->name ?? '—') . ($student->assignedTeacher ? ' (' . $student->assignedTeacher->code . ')' : '')],
                                ['Ruangan',     ($student->assignedRoom?->name ?? '—') . ($student->assignedRoom ? ' (' . $student->assignedRoom->code . ')' : '')],
                                ['Jadwal',      $jadwalText],
                                ['Aktif Sejak', $student->active_since?->format('d M Y') ?? '—'],
                                ['Trial',       $student->trial_date?->format('d M Y, H:i') ?? '—'],
                            ] as [$label, $value])
                            <div class="flex gap-3">
                                <div class="text-xs text-gray-500 w-28 shrink-0 pt-0.5">{{ $label }}</div>
                                <div class="text-sm text-gray-800 flex-1">{{ $value }}</div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Tracking --}}
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                        <div class="text-[10px] uppercase tracking-widest font-semibold mb-4"
                             style="color:#D4A853">Tracking</div>
                        <div class="space-y-3">
                            <div class="flex gap-3">
                                <div class="text-xs text-gray-500 w-28 shrink-0 pt-0.5">Sesi Terakhir</div>
                                <div class="text-sm text-gray-800 flex-1">
                                    {{ $student->last_session_at?->format('d M Y, H:i') ?? '—' }}
                                    @if($student->last_session_at)
                                    <span class="text-xs text-gray-400 ml-1">({{ $student->last_session_at->diffForHumans() }})</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="text-xs text-gray-500 w-28 shrink-0 pt-0.5">Terdaftar</div>
                                <div class="text-sm text-gray-800">{{ $student->created_at->format('d M Y') }}</div>
                            </div>
                            @if($student->notes)
                            <div class="flex gap-3">
                                <div class="text-xs text-gray-500 w-28 shrink-0 pt-0.5">Catatan</div>
                                <div class="text-sm text-gray-800 whitespace-pre-line flex-1">{{ $student->notes }}</div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== TAB: KELAS ===== --}}
            <div x-show="activeTab === 'kelas'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0">
                @include('students.partials.tab-kelas')
            </div>

            {{-- ===== TAB: JADWAL & SESI ===== --}}
            <div x-show="activeTab === 'jadwal'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="space-y-5">

                {{-- Enrollment aktif --}}
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="text-[10px] uppercase tracking-widest font-semibold mb-3" style="color:#D4A853">Enrollment Aktif</div>
                    @if($activeEnrollment)
                    <div class="rounded-lg p-3 text-sm"
                         style="background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.2)">
                        <span class="text-gray-500">Paket:</span>
                        <span class="font-mono font-semibold text-gray-800 ml-1">{{ $activeEnrollment->package->code ?? '?' }}</span>
                        <span class="text-gray-500 ml-2">·</span>
                        <span class="text-gray-700 ml-2">{{ $activeEnrollment->package->instrument->name ?? '?' }}</span>
                        <span class="text-gray-500 ml-2">· Guru:</span>
                        <span class="font-semibold text-gray-800 ml-1">{{ $activeEnrollment->teacher->name ?? '?' }}</span>
                        <span class="text-xs text-gray-400 ml-2">(sejak {{ $activeEnrollment->effective_date?->format('d M Y') }})</span>
                    </div>
                    @else
                    <div class="rounded-lg p-3 text-sm text-gray-500"
                         style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07)">
                        Belum ada enrollment aktif. Ubah status murid ke Aktif lewat panel Lifecycle di atas.
                    </div>
                    @endif
                </div>

                {{-- Jadwal Mingguan --}}
                @if($activeEnrollment)
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex justify-between items-center mb-3">
                        <div class="text-[10px] uppercase tracking-widest font-semibold" style="color:#D4A853">Jadwal Mingguan Tetap</div>
                        <button type="button"
                                @click="openSchedule = openSchedule === 'create' ? null : 'create'"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                                style="background:rgba(212,168,83,0.15);color:#D4A853">
                            + Tambah Jadwal
                        </button>
                    </div>

                    {{-- Form tambah jadwal --}}
                    <div x-show="openSchedule === 'create'" x-cloak
                         x-data="{
                             selectedDay: '',
                             startTime: '',
                             endTime: '',
                             rooms: {{ Js::from($roomsForFilter) }},
                             booked: {{ Js::from($bookedSchedules) }},
                             instrument: {{ Js::from($studentInstrument) }},
                             get availableRooms() {
                                 return this.rooms.filter(room => {
                                     if (this.instrument &&
                                         !room.supported_instruments.includes(this.instrument)) {
                                         return false;
                                     }
                                     if (!this.selectedDay || !this.startTime || !this.endTime) {
                                         return true;
                                     }
                                     const occupants = this.booked.filter(s =>
                                         s.room_id === room.id &&
                                         s.day_of_week === parseInt(this.selectedDay) &&
                                         s.start_time < this.endTime &&
                                         s.end_time > this.startTime
                                     ).length;
                                     return occupants < room.capacity;
                                 });
                             }
                         }"
                         class="mb-4 rounded-xl p-4"
                         style="background:rgba(212,168,83,0.06);border:1px solid rgba(212,168,83,0.2)">
                        <form method="POST" action="{{ route('schedules.store', $student->id) }}">
                            @csrf
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Hari <span class="text-red-400">*</span></label>
                                    <select name="day_of_week" x-model="selectedDay" required class="block w-full rounded-lg text-sm px-2 py-1.5">
                                        <option value="">—</option>
                                        @foreach(\App\Models\Schedule::DAY_NAMES as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Mulai <span class="text-red-400">*</span></label>
                                    <input type="time" name="start_time" x-model="startTime" required class="block w-full rounded-lg text-sm px-2 py-1.5">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Selesai <span class="text-red-400">*</span></label>
                                    <input type="time" name="end_time" x-model="endTime" required class="block w-full rounded-lg text-sm px-2 py-1.5">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1">Ruangan</label>
                                    <select name="room_id" class="block w-full rounded-lg text-sm px-2 py-1.5">
                                        <option value="">— Pilih —</option>
                                        <template x-for="r in availableRooms" :key="r.id">
                                            <option :value="r.id"
                                                    x-text="`[${r.code}] ${r.name} (kap. ${r.capacity})`">
                                            </option>
                                        </template>
                                    </select>
                                    <p class="text-xs mt-1"
                                       x-show="instrument && availableRooms.length === 0 && (selectedDay || startTime)"
                                       style="color:#F87171">
                                        Tidak ada ruangan tersedia untuk slot &amp; instrumen ini.
                                    </p>
                                    <p class="text-xs mt-1 text-gray-400"
                                       x-show="instrument && availableRooms.length > 0"
                                       x-text="`Menampilkan ruangan yang support ${instrument}`">
                                    </p>
                                    <p class="text-xs mt-1" style="color:#FBBF24"
                                       x-show="!instrument">
                                        Murid belum punya paket aktif — semua ruangan ditampilkan.
                                    </p>
                                </div>
                                <div class="col-span-2 md:col-span-5">
                                    <label class="block text-xs text-gray-500 mb-1">Catatan</label>
                                    <input type="text" name="notes" maxlength="500" class="block w-full rounded-lg text-sm px-2 py-1.5">
                                </div>
                            </div>
                            <button type="submit" class="mt-2 px-3 py-1.5 rounded-lg text-xs font-semibold"
                                    style="background:rgba(212,168,83,0.2);color:#D4A853">
                                Simpan Jadwal
                            </button>
                        </form>
                    </div>

                    @if($activeEnrollment->schedules->isEmpty())
                    <p class="text-sm text-gray-400">Belum ada jadwal. Klik "+ Tambah Jadwal" di atas.</p>
                    @else
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Hari</th>
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Jam</th>
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Ruang</th>
                                <th class="pb-2 text-center text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Status</th>
                                <th class="pb-2 text-right text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeEnrollment->schedules as $sch)
                            <tr class="border-b border-gray-100 {{ $sch->is_active ? '' : 'opacity-50' }}">
                                <td class="py-2 text-gray-700">{{ $sch->day_name }}</td>
                                <td class="py-2 font-mono text-gray-700">
                                    {{ \Carbon\Carbon::parse($sch->start_time)->format('H:i') }} -
                                    {{ \Carbon\Carbon::parse($sch->end_time)->format('H:i') }}
                                </td>
                                <td class="py-2 text-gray-500">{{ $sch->room ? '[' . $sch->room->code . '] ' . $sch->room->name : '—' }}</td>
                                <td class="py-2 text-center">
                                    @if($sch->is_active)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                          style="background:rgba(52,211,153,0.12);color:#34D399">Aktif</span>
                                    @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                          style="background:rgba(139,146,168,0.12);color:#8B92A8">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right space-x-2 whitespace-nowrap">
                                    <form method="POST" action="{{ route('schedules.toggle-active', $sch->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs hover:underline" style="color:#FBBF24">
                                            {{ $sch->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('schedules.destroy', $sch->id) }}" class="inline"
                                          onsubmit="return confirm('Hapus jadwal ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs hover:underline" style="color:#F87171">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="text-xs text-gray-400 mt-2">
                        Nonaktifkan menghentikan generator sesi baru. Hapus hanya bisa jika belum ada sesi ter-generate.
                    </p>
                    @endif
                </div>
                @endif

                {{-- Sesi Mendatang --}}
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5"
                     x-data="{ editSession: null }">
                    @php $canEdit = auth()->user()?->hasAnyRole(['Owner', 'Admin']); @endphp
                    @if($errors->any())
                    <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
                        @foreach($errors->all() as $e)
                            <p>{{ $e }}</p>
                        @endforeach
                    </div>
                    @endif
                    <div class="flex justify-between items-center mb-3">
                        <div class="text-[10px] uppercase tracking-widest font-semibold" style="color:#D4A853">Sesi Mendatang</div>
                        <a href="{{ route('sessions.index', ['student_id' => $student->id]) }}"
                           class="text-xs text-indigo-600 hover:underline">Lihat semua sesi →</a>
                    </div>
                    @if($upcomingSessions->isEmpty())
                    <p class="text-sm text-gray-400">
                        Belum ada sesi yang dijadwalkan. Generate via
                        <code class="text-xs px-1 rounded" style="background:rgba(255,255,255,0.06)">php artisan sessions:generate-month</code>
                    </p>
                    @else
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Tanggal</th>
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Jam</th>
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Label</th>
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Ruang</th>
                                <th class="pb-2 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Guru</th>
                                <th class="pb-2 text-center text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Status</th>
                                @if($canEdit)
                                <th class="pb-2 text-center text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($upcomingSessions as $sess)
                            @php $sCfg = $sessionStatusCfg[$sess->status] ?? $sessionStatusCfg['SCHEDULED']; @endphp
                            <tr class="border-b border-gray-100">
                                <td class="py-2 text-gray-700">{{ \Carbon\Carbon::parse($sess->session_date)->format('D, d M Y') }}</td>
                                <td class="py-2 font-mono text-gray-500">
                                    {{ \Carbon\Carbon::parse($sess->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($sess->end_time)->format('H:i') }}
                                </td>
                                <td class="py-2">
                                    @php $label = $sess->getSessionLabel(); @endphp
                                    @if($label !== '—')
                                        <span class="text-[10px] font-medium
                                            {{ $sess->origin_session_id ? 'text-blue-500' : '' }}"
                                            @if(!$sess->origin_session_id) style="color:#D4A853" @endif>
                                            {{ $label }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 text-gray-500">{{ $sess->room?->code ?? '—' }}</td>
                                <td class="py-2 text-gray-700">
                                    @if($sess->substituteTeacher)
                                    {{ $sess->substituteTeacher->name }}
                                    <span class="text-gray-400">(pengganti)</span>
                                    @else
                                    {{ $student->assignedTeacher?->name ?? '—' }}
                                    @endif
                                </td>
                                <td class="py-2 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                          style="background:{{ $sCfg['bg'] }};color:{{ $sCfg['color'] }}">
                                        {{ $sess->status }}
                                    </span>
                                </td>
                                @if($canEdit)
                                <td class="py-2 text-center whitespace-nowrap">
                                    @if($sess->status === 'SCHEDULED')
                                    <button type="button"
                                            @click="editSession = {
                                                id: {{ $sess->id }},
                                                action: '{{ route('sessions.update', $sess->id) }}',
                                                sessionDate: '{{ \Carbon\Carbon::parse($sess->session_date)->format('D, d M Y') }}',
                                                startTime: '{{ \Carbon\Carbon::parse($sess->start_time)->format('H:i') }}',
                                                endTime: '{{ \Carbon\Carbon::parse($sess->end_time)->format('H:i') }}',
                                                teacherId: {{ $sess->teacher_id ?? 'null' }},
                                                roomId: {{ $sess->room_id ?? 'null' }}
                                            }"
                                            class="text-[10px] text-indigo-600 hover:underline">
                                        Edit
                                    </button>
                                    @endif
                                    @if(in_array($sess->status, ['SCHEDULED', 'LIBUR']))
                                    <form method="POST"
                                          action="{{ route('sessions.destroy', $sess->id) }}"
                                          class="inline ml-1"
                                          onsubmit="return confirm('Hapus sesi {{ \Carbon\Carbon::parse($sess->session_date)->format('d M Y') }} ({{ $sess->status }})?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-[10px] text-red-600 hover:underline">Hapus</button>
                                    </form>
                                    @endif
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif

                    {{-- Modal edit sesi dari halaman detail murid --}}
                    @if($canEdit)
                    <div x-show="editSession !== null" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                         @click.self="editSession = null">
                        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-sm font-semibold text-gray-800">
                                    Edit Sesi — <span x-text="editSession?.sessionDate" class="font-mono"></span>
                                </h3>
                                <button @click="editSession = null"
                                        class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                            </div>

                            <form :action="editSession?.action" method="POST" class="space-y-4">
                                @csrf
                                @method('PATCH')

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Jam Mulai</label>
                                        <input type="time" name="start_time"
                                               :value="editSession?.startTime"
                                               required
                                               class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Jam Selesai</label>
                                        <input type="time" name="end_time"
                                               :value="editSession?.endTime"
                                               required
                                               class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Guru</label>
                                    <select name="teacher_id" required
                                            class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                                        <option value="">— Pilih Guru —</option>
                                        @foreach($teachers as $t)
                                        <option value="{{ $t->id }}"
                                                :selected="editSession?.teacherId == {{ $t->id }}">
                                            {{ $t->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        Ruang <span class="text-gray-400">(opsional)</span>
                                    </label>
                                    <select name="room_id"
                                            class="block w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                                        <option value="">— Tidak Ditentukan —</option>
                                        @foreach($rooms as $r)
                                        <option value="{{ $r->id }}"
                                                :selected="editSession?.roomId == {{ $r->id }}">
                                            {{ $r->code }} — {{ $r->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="editSession = null"
                                            class="px-4 py-2 text-xs bg-gray-100 rounded-lg hover:bg-gray-200">
                                        Batal
                                    </button>
                                    <button type="submit"
                                            class="px-4 py-2 text-xs font-bold rounded-lg btn-mk-primary">
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ===== TAB: TAGIHAN ===== --}}
            <div x-show="activeTab === 'tagihan'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="space-y-4">

                {{-- Ringkasan saldo --}}
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                        <div class="text-[10px] uppercase tracking-wide text-gray-500 mb-1">Saldo Outstanding</div>
                        <div class="text-xl font-bold leading-none {{ $outstandingBalance > 0 ? '' : '' }}"
                             style="color:{{ $outstandingBalance > 0 ? '#F87171' : '#34D399' }}">
                            Rp {{ number_format($outstandingBalance, 0, ',', '.') }}
                        </div>
                        <div class="text-xs text-gray-400 mt-1">{{ $unpaidCount }} tagihan belum lunas</div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                        <div class="text-[10px] uppercase tracking-wide text-gray-500 mb-1">Total Invoice</div>
                        <div class="text-xl font-bold text-gray-800 leading-none">{{ $student->invoices->count() }}</div>
                        <div class="text-xs text-gray-400 mt-1">sepanjang waktu</div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center justify-center">
                        <a href="{{ route('invoices.index', ['student_id' => $student->id]) }}"
                           class="text-xs text-indigo-600 hover:underline text-center">
                            Lihat semua tagihan →
                        </a>
                    </div>
                </div>

                {{-- Kartu Cicilan Kids Bundle (hanya muncul jika primary enrollment = KIDS_CLASS_BUNDLE INSTALLMENT) --}}
                @if(!empty($kidsInstallments) && $kidsInstallments->isNotEmpty())
                @php
                    $kPaidCount    = $kidsInstallments->where('status', 'PAID')->count();
                    $kTotalCount   = $kidsInstallments->count();
                    $kNonVoidCount = $kidsInstallments->where('status', '!=', 'VOID')->count();
                    $kTotalAmount  = $kidsInstallments->where('status', '!=', 'VOID')->sum('total_amount');
                    $kPaidAmount   = $kidsInstallments->sum('paid_amount');
                    // Lunas = semua termin non-VOID sudah PAID (termin VOID tidak dihitung)
                    $kAllPaid      = $kNonVoidCount > 0 && $kPaidCount === $kNonVoidCount;
                    // Termin berikutnya yang perlu dibayar (untuk warna gold di progress bar)
                    $kNextNumber   = !$kAllPaid
                        ? $kidsInstallments->where('status', '!=', 'PAID')->min('installment_number')
                        : null;
                @endphp
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-[10px] uppercase tracking-widest font-semibold" style="color:#D4A853">
                            Cicilan Kids Class Bundle
                        </div>
                        @if($kAllPaid)
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-green-50 text-green-700">
                                Lunas ✓
                            </span>
                        @else
                            <span class="text-[10px] text-gray-400">
                                {{ $kPaidCount }}/{{ $kTotalCount }} termin lunas
                            </span>
                        @endif
                    </div>
                    <div class="px-5 py-3">
                        {{-- Subtitle nominal --}}
                        <div class="text-xs text-gray-500 mb-2">
                            Rp {{ number_format($kPaidAmount, 0, ',', '.') }} dibayar
                            dari Rp {{ number_format($kTotalAmount, 0, ',', '.') }}
                        </div>

                        {{-- Progress bar --}}
                        <div class="flex gap-1 h-1.5 rounded-full overflow-hidden mb-3">
                            @foreach($kidsInstallments as $kInv)
                            @php
                                $kIsNext = $kInv->installment_number === $kNextNumber;
                                if ($kInv->status === 'PAID') {
                                    $kSegColor = 'bg-green-400';
                                } elseif ($kIsNext) {
                                    $kSegColor = 'bg-yellow-400';
                                } else {
                                    $kSegColor = 'bg-gray-200';
                                }
                            @endphp
                            <div class="flex-1 rounded-sm {{ $kSegColor }}"></div>
                            @endforeach
                        </div>

                        {{-- Tabel 3 termin (semua baris klikable) --}}
                        <div class="space-y-1">
                            @foreach($kidsInstallments as $kInv)
                            @php
                                $kDotColor = match($kInv->status) {
                                    'PAID'    => 'bg-green-400',
                                    'PARTIAL' => 'bg-yellow-400',
                                    'VOID'    => 'bg-gray-200',
                                    default   => 'bg-gray-300',
                                };
                                $kBadgeClass = match($kInv->status) {
                                    'PAID'    => 'bg-green-50 text-green-700',
                                    'PARTIAL' => 'bg-yellow-50 text-yellow-800',
                                    'VOID'    => 'bg-gray-100 text-gray-400 line-through',
                                    default   => 'bg-gray-100 text-gray-500',
                                };
                                $kBadgeText = match($kInv->status) {
                                    'PAID'    => 'LUNAS',
                                    'PARTIAL' => 'SEBAGIAN',
                                    'VOID'    => 'VOID',
                                    default   => 'BELUM BAYAR',
                                };
                            @endphp
                            <a href="{{ route('invoices.show', $kInv->id) }}"
                               class="flex items-center gap-3 px-2 py-1.5 rounded-lg text-xs hover:bg-gray-50 transition-colors">
                                <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $kDotColor }}"></div>
                                <div class="flex-1 text-gray-700">
                                    Termin {{ $kInv->installment_number }}/3
                                    <span class="text-gray-400 ml-1">· {{ $kInv->due_date?->format('d M Y') ?? '—' }}</span>
                                </div>
                                <div class="font-mono text-gray-600">
                                    Rp {{ number_format($kInv->total_amount, 0, ',', '.') }}
                                </div>
                                <span class="px-1.5 py-0.5 rounded {{ $kBadgeClass }} text-[10px] font-semibold">
                                    {{ $kBadgeText }}
                                </span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- Invoice terbaru --}}
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100">
                        <div class="text-[10px] uppercase tracking-widest font-semibold" style="color:#D4A853">5 Tagihan Terbaru</div>
                    </div>
                    @if($recentInvoices->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-gray-400">Belum ada tagihan.</div>
                    @else
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th class="px-4 py-2.5 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">No. Invoice</th>
                                <th class="px-4 py-2.5 text-left text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Items</th>
                                <th class="px-4 py-2.5 text-right text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Total</th>
                                <th class="px-4 py-2.5 text-right text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Saldo</th>
                                <th class="px-4 py-2.5 text-center text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Status</th>
                                <th class="px-4 py-2.5 text-center text-[10px] uppercase tracking-wide text-gray-500 font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentInvoices as $inv)
                            @php $iCfg = $invStatusCfg[$inv->status] ?? ['bg'=>'rgba(139,146,168,0.12)','color'=>'#8B92A8']; @endphp
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-2.5 font-mono" style="color:#D4A853">{{ $inv->invoice_number }}</td>
                                <td class="px-4 py-2.5">
                                    @foreach($inv->items as $item)
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold mr-1"
                                          style="background:rgba(255,255,255,0.06);color:#8B92A8">{{ $item->item_code }}</span>
                                    @endforeach
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-700 font-mono">
                                    Rp {{ number_format($inv->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono font-semibold"
                                    style="color:{{ $inv->balance > 0 ? '#F87171' : '#34D399' }}">
                                    Rp {{ number_format($inv->balance, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                          style="background:{{ $iCfg['bg'] }};color:{{ $iCfg['color'] }}">
                                        {{ $inv->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <a href="{{ route('invoices.show', $inv->id) }}"
                                       class="px-2.5 py-1 rounded-lg text-[10px] font-semibold"
                                       style="background:rgba(212,168,83,0.15);color:#D4A853">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>

            {{-- ===== TAB: RIWAYAT STATUS ===== --}}
            <div x-show="activeTab === 'history'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0">

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="text-[10px] uppercase tracking-widest font-semibold mb-5" style="color:#D4A853">
                        Riwayat Perubahan Status
                    </div>

                    @if($student->histories->isEmpty())
                    <p class="text-sm text-gray-400">Belum ada perubahan status tercatat.</p>
                    @else
                    <div class="relative pl-6">
                        {{-- Garis vertikal timeline --}}
                        <div class="absolute left-[7px] top-2 bottom-2 w-0.5"
                             style="background:rgba(255,255,255,0.07)"></div>

                        @foreach($student->histories as $h)
                        @php
                            $hCfg = $statusCfg[$h->to_status] ?? $statusCfg['Calon'];
                        @endphp
                        <div class="relative mb-5 last:mb-0">
                            {{-- Dot --}}
                            <div class="absolute left-[-22px] top-[5px] w-3.5 h-3.5 rounded-full"
                                 style="background:{{ $hCfg['dot'] }};border:3px solid #1E2235"></div>

                            {{-- Card --}}
                            <div class="rounded-xl p-4" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07)">
                                <div class="flex justify-between items-start gap-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        @if($h->from_status)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                              style="background:{{ ($statusCfg[$h->from_status] ?? $statusCfg['Calon'])['bg'] }};color:{{ ($statusCfg[$h->from_status] ?? $statusCfg['Calon'])['color'] }}">
                                            {{ $h->from_status }}
                                        </span>
                                        <span class="text-gray-400 text-sm">→</span>
                                        @endif
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                              style="background:{{ $hCfg['bg'] }};color:{{ $hCfg['color'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $hCfg['dot'] }}"></span>
                                            {{ $h->to_status }}
                                        </span>
                                        @if($h->skipped_trial)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                              style="background:rgba(251,191,36,0.12);color:#FBBF24">
                                            ⚡ Skip Trial
                                        </span>
                                        @endif
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-xs text-gray-400">{{ $h->created_at->format('d M Y, H:i') }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            {{ $h->changedBy ? 'oleh ' . $h->changedBy->name : 'sistem' }}
                                        </div>
                                    </div>
                                </div>

                                @if($h->reason)
                                <p class="mt-2 text-sm text-gray-600 whitespace-pre-line">{{ $h->reason }}</p>
                                @endif

                                @if($h->metadata)
                                <div class="mt-2 space-y-0.5">
                                    @if(!empty($h->metadata['reason_code']))
                                    <div class="text-xs text-gray-400">
                                        Kode: <code class="px-1 rounded" style="background:rgba(255,255,255,0.06)">{{ $h->metadata['reason_code'] }}</code>
                                    </div>
                                    @endif
                                    @if(!empty($h->metadata['cuti_from']))
                                    <div class="text-xs text-gray-400">
                                        Cuti: {{ \Carbon\Carbon::parse($h->metadata['cuti_from'])->format('d M Y') }}
                                        s/d {{ \Carbon\Carbon::parse($h->metadata['cuti_until'])->format('d M Y') }}
                                        @if(!empty($h->metadata['is_extension'])) (perpanjangan)@endif
                                    </div>
                                    @endif
                                    @if(!empty($h->metadata['trial_date']))
                                    <div class="text-xs text-gray-400">
                                        Trial: {{ \Carbon\Carbon::parse($h->metadata['trial_date'])->format('d M Y, H:i') }}
                                    </div>
                                    @endif
                                    @if(!empty($h->metadata['pending_invoices']))
                                    <div class="text-xs text-gray-400">
                                        Tagihan: @foreach($h->metadata['pending_invoices'] as $code)
                                        <code class="px-1 rounded ml-0.5" style="background:rgba(255,255,255,0.06)">{{ $code }}</code>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

        </div>{{-- end x-data tabs --}}

    </div>

<script>
/**
 * Komponen Alpine.data untuk form lifecycle yang membutuhkan filter guru.
 * Didaftarkan via Alpine.data() agar this-binding ke proxy reaktif bekerja benar.
 * Dipakai di 3 form: Skip Trial, Konversi Aktif, Re-aktivasi.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('lifecycleTeacherFilter', () => ({
        kidsBundle: false,
        teachers: [],
        loadingTeachers: false,
        filterTeachers(instrId, classType) {
            this.kidsBundle = (classType === 'KIDS_CLASS_BUNDLE');
            if (!instrId) { this.teachers = []; return; }
            this.loadingTeachers = true;
            this.teachers = [];
            fetch('{{ route('api.teachers-by-instrument', '') }}/' + instrId)
                .then(r => r.json())
                .then(data => { this.teachers = data; this.loadingTeachers = false; })
                .catch(() => { this.teachers = []; this.loadingTeachers = false; });
        }
    }));
});
</script>
</x-app-layout>
