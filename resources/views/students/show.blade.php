<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Detail Murid: {{ $student->full_name }}</h2>
            <a href="{{ route('students.index') }}" class="text-sm text-gray-600 hover:underline">
                ← Kembali ke daftar
            </a>
        </div>
    </x-slot>

    @php
        // Pemetaan warna status — dipakai di header card & timeline history
        $statusColors = [
            'Calon' => 'bg-gray-100 text-gray-700 border-gray-300',
            'Trial' => 'bg-purple-100 text-purple-700 border-purple-300',
            'Aktif' => 'bg-green-100 text-green-700 border-green-300',
            'Cuti' => 'bg-amber-100 text-amber-700 border-amber-300',
            'Selesai' => 'bg-blue-100 text-blue-700 border-blue-300',
            'Mengundurkan Diri' => 'bg-red-100 text-red-700 border-red-300',
        ];

        // Cek apakah paket murid Kids Class — untuk munculkan tombol "Selesai"
        $isKidsClass = $student->package
            && in_array($student->package->class_type, ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE']);

        // Filter guru by instrumen paket murid (untuk dropdown re-enroll)
        $instrumentId = $student->package?->instrument_id;
    @endphp

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded">
                    <strong>Form aksi gagal divalidasi:</strong>
                    <ul class="list-disc pl-5 mt-1 text-sm">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ============= HEADER CARD ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-mono text-sm text-gray-500">{{ $student->student_code }}</div>
                        <div class="text-2xl font-bold mt-1">{{ $student->full_name }}</div>
                        @if($student->nickname)
                            <div class="text-gray-600">"{{ $student->nickname }}"</div>
                        @endif
                        <div class="mt-2">
                            <span class="px-3 py-1 rounded text-sm font-medium border {{ $statusColors[$student->status] ?? '' }}">
                                {{ $student->status }}
                            </span>
                        </div>
                    </div>
                    <a href="{{ route('students.edit', $student->id) }}"
                       class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Edit Murid
                    </a>
                </div>
            </div>

            {{-- ============= PANEL AKSI LIFECYCLE ============= --}}
            {{-- Tombol berbeda per status. Form muncul inline pakai Alpine x-show. --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6"
                 x-data="{ openForm: null }">

                <h3 class="text-lg font-medium mb-3">Aksi Lifecycle</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Setiap perubahan status akan tercatat di Riwayat Status di bawah.
                </p>

                {{-- ===== Tombol pemicu — kondisional per status ===== --}}
                <div class="flex flex-wrap gap-2">

                    @if($student->status === 'Calon')
                        <button type="button" @click="openForm = openForm === 'trial' ? null : 'trial'"
                                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded text-sm">
                            Mulai Trial
                        </button>
                        <button type="button" @click="openForm = openForm === 'skip' ? null : 'skip'"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                            Skip Trial → Aktif
                        </button>
                    @endif

                    @if($student->status === 'Trial')
                        <button type="button" @click="openForm = openForm === 'convert' ? null : 'convert'"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                            Konversi → Aktif
                        </button>
                    @endif

                    @if($student->status === 'Aktif')
                        <button type="button" @click="openForm = openForm === 'cuti' ? null : 'cuti'"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded text-sm">
                            Ajukan Cuti
                        </button>
                        @if($isKidsClass)
                            <button type="button" @click="openForm = openForm === 'complete' ? null : 'complete'"
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                Tandai Selesai (Lulus Kids)
                            </button>
                        @endif
                    @endif

                    @if($student->status === 'Cuti')
                        <form method="POST" action="{{ route('students.return-from-cuti', $student->id) }}"
                              onsubmit="return confirm('Akhiri cuti dan kembalikan ke Aktif?')"
                              class="inline">
                            @csrf
                            <button type="submit"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                                Akhiri Cuti → Aktif
                            </button>
                        </form>
                        <button type="button" @click="openForm = openForm === 'cuti' ? null : 'cuti'"
                                class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded text-sm">
                            Perpanjang Cuti
                        </button>
                    @endif

                    @if(in_array($student->status, ['Selesai', 'Mengundurkan Diri']))
                        <button type="button" @click="openForm = openForm === 'reactivate' ? null : 'reactivate'"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                            @if($student->status === 'Selesai')
                                Re-enroll Privat (tanpa registrasi ulang)
                            @else
                                Re-aktivasi (bayar registrasi ulang)
                            @endif
                        </button>
                    @endif

                    {{-- Tombol Mundur tersedia di hampir semua status aktif --}}
                    @if(in_array($student->status, ['Calon', 'Trial', 'Aktif', 'Cuti']))
                        <button type="button" @click="openForm = openForm === 'withdraw' ? null : 'withdraw'"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm">
                            Tandai Mundur
                        </button>
                    @endif

                    @if($student->status === 'Mengundurkan Diri' || $student->status === 'Selesai')
                        <span class="text-xs text-gray-500 self-center">
                            Status terminal — gunakan tombol re-aktivasi di atas untuk membuka kembali.
                        </span>
                    @endif
                </div>

                {{-- ============================================================ --}}
                {{-- ===== Form-form aksi (toggle via Alpine x-show) ============= --}}
                {{-- ============================================================ --}}

                {{-- ----- FORM: MULAI TRIAL ----- --}}
                <div x-show="openForm === 'trial'" x-cloak
                     class="mt-4 p-4 border border-purple-200 bg-purple-50 rounded">
                    <form method="POST" action="{{ route('students.start-trial', $student->id) }}">
                        @csrf
                        <h4 class="font-medium mb-3">Jadwalkan Trial (durasi 30 menit, BR-1.3)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div>
                                <label class="block">Tanggal & Jam Trial <span class="text-red-500">*</span></label>
                                <input type="datetime-local" name="trial_date" required
                                       min="{{ now()->addHour()->format('Y-m-d\TH:i') }}"
                                       class="mt-1 block w-full border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block">Paket yang Diminati</label>
                                <select name="package_id" class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($packages as $pkg)
                                        <option value="{{ $pkg->id }}"
                                            {{ $student->package_id == $pkg->id ? 'selected' : '' }}>
                                            [{{ $pkg->code }}] {{ $pkg->instrument->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block">Guru Trial</label>
                                <select name="assigned_teacher_id" class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($teachers as $t)
                                        <option value="{{ $t->id }}"
                                            {{ $student->assigned_teacher_id == $t->id ? 'selected' : '' }}>
                                            [{{ $t->code }}] {{ $t->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block">Ruangan</label>
                                <select name="assigned_room_id" class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($rooms as $r)
                                        <option value="{{ $r->id }}"
                                            {{ $student->assigned_room_id == $r->id ? 'selected' : '' }}>
                                            [{{ $r->code }}] {{ $r->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block">Catatan</label>
                                <textarea name="notes" rows="2" maxlength="500"
                                          class="mt-1 block w-full border-gray-300 rounded"></textarea>
                            </div>
                        </div>
                        <button type="submit"
                                class="mt-3 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded text-sm">
                            Simpan Jadwal Trial
                        </button>
                    </form>
                </div>

                {{-- ----- FORM: SKIP TRIAL (Calon → Aktif) ----- --}}
                <div x-show="openForm === 'skip'" x-cloak
                     class="mt-4 p-4 border border-green-200 bg-green-50 rounded">
                    <form method="POST" action="{{ route('students.skip-trial', $student->id) }}">
                        @csrf
                        <h4 class="font-medium mb-1">Skip Trial → Langsung Aktif</h4>
                        <p class="text-xs text-gray-600 mb-3">
                            Jalur Hybrid: murid langsung daftar tanpa trial. Tagihan REG + SPP otomatis di-flag.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="md:col-span-2">
                                <label class="block">Kode Alasan <span class="text-red-500">*</span></label>
                                <select name="reason_code" required class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    <option value="walk_in">Walk-in (datang langsung confident)</option>
                                    <option value="migrasi">Migrasi data sistem lama</option>
                                    <option value="reaktivasi">Reaktivasi murid lama</option>
                                    <option value="lulus_kids">Lulus Kids Class lanjut privat</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block">Penjelasan Detail <span class="text-red-500">*</span></label>
                                <textarea name="reason" required rows="2" maxlength="500"
                                          class="mt-1 block w-full border-gray-300 rounded"
                                          placeholder="Konteks tambahan untuk audit trail."></textarea>
                            </div>
                            <div>
                                <label class="block">Paket <span class="text-red-500">*</span></label>
                                <select name="package_id" required class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($packages as $pkg)
                                        <option value="{{ $pkg->id }}">
                                            [{{ $pkg->code }}] {{ $pkg->instrument->name }} ({{ $pkg->formatted_price }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block">Guru Utama <span class="text-red-500">*</span></label>
                                <select name="assigned_teacher_id" required class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($teachers as $t)
                                        <option value="{{ $t->id }}">
                                            [{{ $t->code }}] {{ $t->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block">Ruangan</label>
                                <select name="assigned_room_id" class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($rooms as $r)
                                        <option value="{{ $r->id }}">[{{ $r->code }}] {{ $r->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit"
                                class="mt-3 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                            Konfirmasi Skip Trial
                        </button>
                    </form>
                </div>

                {{-- ----- FORM: KONVERSI AKTIF (Trial → Aktif) ----- --}}
                <div x-show="openForm === 'convert'" x-cloak
                     class="mt-4 p-4 border border-green-200 bg-green-50 rounded">
                    <form method="POST" action="{{ route('students.convert-active', $student->id) }}">
                        @csrf
                        <h4 class="font-medium mb-1">Konversi Trial → Aktif</h4>
                        <p class="text-xs text-gray-600 mb-3">
                            Trial sukses, murid lanjut daftar penuh. Tagihan REG (Rp 250.000) + SPP bulan pertama akan di-flag.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div>
                                <label class="block">Paket <span class="text-red-500">*</span></label>
                                <select name="package_id" required class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($packages as $pkg)
                                        <option value="{{ $pkg->id }}"
                                            {{ $student->package_id == $pkg->id ? 'selected' : '' }}>
                                            [{{ $pkg->code }}] {{ $pkg->instrument->name }} ({{ $pkg->formatted_price }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block">Guru Utama <span class="text-red-500">*</span></label>
                                <select name="assigned_teacher_id" required class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($teachers as $t)
                                        <option value="{{ $t->id }}"
                                            {{ $student->assigned_teacher_id == $t->id ? 'selected' : '' }}>
                                            [{{ $t->code }}] {{ $t->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block">Ruangan</label>
                                <select name="assigned_room_id" class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($rooms as $r)
                                        <option value="{{ $r->id }}"
                                            {{ $student->assigned_room_id == $r->id ? 'selected' : '' }}>
                                            [{{ $r->code }}] {{ $r->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block">Catatan</label>
                                <textarea name="notes" rows="2" maxlength="500"
                                          class="mt-1 block w-full border-gray-300 rounded"></textarea>
                            </div>
                        </div>
                        <button type="submit"
                                class="mt-3 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                            Konfirmasi Konversi Aktif
                        </button>
                    </form>
                </div>

                {{-- ----- FORM: AJUKAN / PERPANJANG CUTI ----- --}}
                <div x-show="openForm === 'cuti'" x-cloak
                     class="mt-4 p-4 border border-amber-200 bg-amber-50 rounded">
                    <form method="POST" action="{{ route('students.start-cuti', $student->id) }}">
                        @csrf
                        <h4 class="font-medium mb-1">
                            @if($student->status === 'Cuti') Perpanjang Cuti @else Ajukan Cuti @endif
                        </h4>
                        <p class="text-xs text-gray-600 mb-3">
                            Biaya Rp 100.000/pengajuan. Maks 1 bulan + perpanjang 1x.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div>
                                <label class="block">Mulai Cuti <span class="text-red-500">*</span></label>
                                <input type="date" name="cuti_from" required
                                       min="{{ now()->toDateString() }}"
                                       class="mt-1 block w-full border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block">Sampai Tanggal <span class="text-red-500">*</span></label>
                                <input type="date" name="cuti_until" required
                                       class="mt-1 block w-full border-gray-300 rounded">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block">Alasan <span class="text-red-500">*</span></label>
                                <textarea name="reason" required rows="2" maxlength="500"
                                          class="mt-1 block w-full border-gray-300 rounded"
                                          placeholder="Mis: UAS sekolah, perjalanan keluarga, dll."></textarea>
                            </div>
                        </div>
                        <button type="submit"
                                class="mt-3 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded text-sm">
                            Simpan Pengajuan Cuti
                        </button>
                    </form>
                </div>

                {{-- ----- FORM: SELESAI (Kids Class lulus) ----- --}}
                <div x-show="openForm === 'complete'" x-cloak
                     class="mt-4 p-4 border border-blue-200 bg-blue-50 rounded">
                    <form method="POST" action="{{ route('students.complete', $student->id) }}">
                        @csrf
                        <h4 class="font-medium mb-1">Tandai Selesai (Lulus Kids Class)</h4>
                        <p class="text-xs text-gray-600 mb-3">
                            Khusus paket Kids Class yang lulus 6 bulan (BR-10.7).
                            Murid bisa re-enroll privat tanpa bayar registrasi ulang.
                        </p>
                        <textarea name="notes" rows="2" maxlength="500"
                                  class="block w-full border-gray-300 rounded text-sm"
                                  placeholder="Catatan kelulusan (opsional)"></textarea>
                        <button type="submit"
                                onclick="return confirm('Tandai murid Selesai?')"
                                class="mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                            Konfirmasi Selesai
                        </button>
                    </form>
                </div>

                {{-- ----- FORM: RE-AKTIVASI / RE-ENROLL ----- --}}
                <div x-show="openForm === 'reactivate'" x-cloak
                     class="mt-4 p-4 border border-green-200 bg-green-50 rounded">
                    <form method="POST" action="{{ route('students.reactivate', $student->id) }}">
                        @csrf
                        <h4 class="font-medium mb-1">
                            @if($student->status === 'Selesai')
                                Re-enroll Privat (tanpa registrasi ulang)
                            @else
                                Re-aktivasi (bayar registrasi ulang Rp 250.000)
                            @endif
                        </h4>
                        <p class="text-xs text-gray-600 mb-3">
                            Pilih paket privat yang akan diambil.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div>
                                <label class="block">Paket <span class="text-red-500">*</span></label>
                                <select name="package_id" required class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($packages as $pkg)
                                        <option value="{{ $pkg->id }}">
                                            [{{ $pkg->code }}] {{ $pkg->instrument->name }} ({{ $pkg->formatted_price }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block">Guru Utama <span class="text-red-500">*</span></label>
                                <select name="assigned_teacher_id" required class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($teachers as $t)
                                        <option value="{{ $t->id }}">[{{ $t->code }}] {{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block">Ruangan</label>
                                <select name="assigned_room_id" class="mt-1 block w-full border-gray-300 rounded">
                                    <option value="">— Pilih —</option>
                                    @foreach($rooms as $r)
                                        <option value="{{ $r->id }}">[{{ $r->code }}] {{ $r->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block">Catatan</label>
                                <textarea name="notes" rows="2" maxlength="500"
                                          class="mt-1 block w-full border-gray-300 rounded"></textarea>
                            </div>
                        </div>
                        <button type="submit"
                                class="mt-3 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                            Konfirmasi Re-aktivasi
                        </button>
                    </form>
                </div>

                {{-- ----- FORM: MUNDUR ----- --}}
                <div x-show="openForm === 'withdraw'" x-cloak
                     class="mt-4 p-4 border border-red-200 bg-red-50 rounded">
                    <form method="POST" action="{{ route('students.withdraw', $student->id) }}"
                          onsubmit="return confirm('Tandai murid Mengundurkan Diri? Aksi ini bisa di-rollback lewat Re-aktivasi.')">
                        @csrf
                        <h4 class="font-medium mb-1">Tandai Murid Mundur</h4>
                        <p class="text-xs text-gray-600 mb-3">
                            Status terminal. Murid bisa di-aktifkan kembali via Re-aktivasi
                            (wajib bayar registrasi ulang Rp 250.000).
                        </p>
                        <label class="block text-sm">Alasan <span class="text-red-500">*</span></label>
                        <textarea name="reason" required rows="2" maxlength="500"
                                  class="mt-1 block w-full border-gray-300 rounded text-sm"
                                  placeholder="Mis: pindah kota, tunggakan >1 bulan, tidak melanjutkan setelah trial"></textarea>
                        <button type="submit"
                                class="mt-3 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm">
                            Konfirmasi Mundur
                        </button>
                    </form>
                </div>
            </div>

            {{-- ============= IDENTITAS & KONTAK ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium mb-4">Identitas & Kontak</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Jenis Kelamin</dt>
                        <dd class="mt-1">{{ $student->gender == 'L' ? 'Laki-laki' : 'Perempuan' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tanggal Lahir</dt>
                        <dd class="mt-1">
                            {{ $student->birth_date?->format('d M Y') ?? '—' }}
                            @if($student->age)
                                <span class="text-gray-500">({{ $student->age }} tahun)</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">No. HP</dt>
                        <dd class="mt-1">{{ $student->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Email</dt>
                        <dd class="mt-1">{{ $student->email ?? '—' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-gray-500">Alamat</dt>
                        <dd class="mt-1">{{ $student->address ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- ============= PARENT/GUARDIAN ============= --}}
            @if($student->parent_name || $student->parent_phone)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium mb-4">Orang Tua / Wali</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500">Nama</dt>
                            <dd class="mt-1">{{ $student->parent_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Hubungan</dt>
                            <dd class="mt-1">{{ $student->parent_relationship ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">No. HP</dt>
                            <dd class="mt-1">{{ $student->parent_phone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Email</dt>
                            <dd class="mt-1">{{ $student->parent_email ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            {{-- ============= STATUS BELAJAR ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium mb-4">Status Belajar</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Paket</dt>
                        <dd class="mt-1">
                            @if($student->package)
                                <span class="font-mono">{{ $student->package->code }}</span><br>
                                <span class="text-gray-500 text-xs">
                                    {{ $student->package->class_type }}
                                    @if($student->package->grade) — {{ $student->package->grade }} @endif
                                    — {{ $student->package->duration_min }} menit
                                </span><br>
                                <span class="text-gray-700">{{ $student->package->formatted_price }}/bulan</span>
                            @else
                                <span class="text-gray-400">— Belum ditentukan —</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Instrumen</dt>
                        <dd class="mt-1">{{ $student->package?->instrument?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Guru Utama</dt>
                        <dd class="mt-1">
                            @if($student->assignedTeacher)
                                {{ $student->assignedTeacher->name }}
                                <span class="text-xs text-gray-500">({{ $student->assignedTeacher->code }})</span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Ruangan</dt>
                        <dd class="mt-1">
                            @if($student->assignedRoom)
                                {{ $student->assignedRoom->name }}
                                <span class="text-xs text-gray-500">({{ $student->assignedRoom->code }})</span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Hari Preferensi</dt>
                        <dd class="mt-1">{{ $student->preferred_day ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Jam Preferensi</dt>
                        <dd class="mt-1">
                            {{ $student->preferred_time ? \Carbon\Carbon::parse($student->preferred_time)->format('H:i') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Jadwal Trial</dt>
                        <dd class="mt-1">{{ $student->trial_date?->format('d M Y, H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Aktif Sejak</dt>
                        <dd class="mt-1">{{ $student->active_since?->format('d M Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- ============= TRACKING ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium mb-4">Tracking</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Sesi Terakhir</dt>
                        <dd class="mt-1">
                            {{ $student->last_session_at?->format('d M Y, H:i') ?? '—' }}
                            @if($student->last_session_at)
                                <span class="text-gray-500">
                                    ({{ $student->last_session_at->diffForHumans() }})
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Terdaftar Sejak</dt>
                        <dd class="mt-1">{{ $student->created_at->format('d M Y') }}</dd>
                    </div>
                    @if($student->notes)
                        <div class="md:col-span-2">
                            <dt class="text-gray-500">Catatan</dt>
                            <dd class="mt-1 whitespace-pre-line">{{ $student->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- ============= ENROLLMENT, JADWAL & SESI (M03) ============= --}}
            @php
                // Enrollment ACTIVE adalah sumber kebenaran untuk M03/M04/M05/M06.
                // Kalau tidak ada (status Calon/Trial/Mundur/Selesai), section ini dikosongkan.
                $activeEnrollment = $student->enrollments->firstWhere('status', 'ACTIVE');
                $sessionStatusColors = [
                    'SCHEDULED'       => 'bg-gray-100 text-gray-700',
                    'HADIR'           => 'bg-green-100 text-green-700',
                    'HADIR_TERLAMBAT' => 'bg-yellow-100 text-yellow-800',
                    'IZIN_RESCHEDULE' => 'bg-blue-100 text-blue-700',
                    'IZIN_VIDEO'      => 'bg-indigo-100 text-indigo-700',
                    'HANGUS'          => 'bg-red-100 text-red-700',
                    'LIBUR'           => 'bg-purple-100 text-purple-700',
                    'DIGANTI'         => 'bg-orange-100 text-orange-700',
                ];
            @endphp

            <div class="bg-white shadow-sm sm:rounded-lg p-6"
                 x-data="{ openSchedule: null }">
                <h3 class="text-lg font-medium mb-3">Enrollment, Jadwal & Sesi</h3>

                {{-- ===== ENROLLMENT ACTIVE ===== --}}
                @if($activeEnrollment)
                    <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded text-sm">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-gray-500">Enrollment aktif:</span>
                                <span class="font-mono">{{ $activeEnrollment->package->code ?? '?' }}</span>
                                · {{ $activeEnrollment->package->instrument->name ?? '?' }}
                                · Guru: <strong>{{ $activeEnrollment->teacher->name ?? '?' }}</strong>
                                <span class="text-xs text-gray-500 ml-1">
                                    (sejak {{ $activeEnrollment->effective_date?->format('d M Y') }})
                                </span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded text-sm text-gray-600">
                        Belum ada enrollment aktif. Jadikan murid Aktif lewat tombol di atas
                        untuk mulai jadwal mingguan.
                    </div>
                @endif

                {{-- ===== JADWAL MINGGUAN ===== --}}
                @if($activeEnrollment)
                    <div class="mb-2 flex justify-between items-center">
                        <h4 class="font-medium text-sm">Jadwal Mingguan Tetap</h4>
                        <button type="button"
                                @click="openSchedule = openSchedule === 'create' ? null : 'create'"
                                class="text-xs px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded">
                            + Tambah Jadwal
                        </button>
                    </div>

                    {{-- Form tambah jadwal --}}
                    <div x-show="openSchedule === 'create'" x-cloak
                         class="mb-3 p-3 border border-blue-200 bg-blue-50 rounded">
                        <form method="POST" action="{{ route('schedules.store', $student->id) }}">
                            @csrf
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
                                <div>
                                    <label class="block text-xs">Hari <span class="text-red-500">*</span></label>
                                    <select name="day_of_week" required class="mt-1 block w-full border-gray-300 rounded text-sm">
                                        <option value="">—</option>
                                        @foreach(\App\Models\Schedule::DAY_NAMES as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs">Mulai <span class="text-red-500">*</span></label>
                                    <input type="time" name="start_time" required
                                           class="mt-1 block w-full border-gray-300 rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs">Selesai <span class="text-red-500">*</span></label>
                                    <input type="time" name="end_time" required
                                           class="mt-1 block w-full border-gray-300 rounded text-sm">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs">Ruangan</label>
                                    <select name="room_id" class="mt-1 block w-full border-gray-300 rounded text-sm">
                                        <option value="">— Pilih —</option>
                                        @foreach($rooms as $r)
                                            <option value="{{ $r->id }}">
                                                [{{ $r->code }}] {{ $r->name }} (kap. {{ $r->capacity }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-2 md:col-span-5">
                                    <label class="block text-xs">Catatan</label>
                                    <input type="text" name="notes" maxlength="500"
                                           class="mt-1 block w-full border-gray-300 rounded text-sm">
                                </div>
                            </div>
                            <button type="submit"
                                    class="mt-2 px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                Simpan Jadwal
                            </button>
                        </form>
                    </div>

                    {{-- Tabel schedule --}}
                    @if($activeEnrollment->schedules->isEmpty())
                        <p class="text-sm text-gray-500">Belum ada jadwal. Klik "+ Tambah Jadwal" di atas.</p>
                    @else
                        <table class="w-full text-sm border">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-1 text-left">Hari</th>
                                    <th class="px-2 py-1 text-left">Jam</th>
                                    <th class="px-2 py-1 text-left">Ruang</th>
                                    <th class="px-2 py-1 text-center">Status</th>
                                    <th class="px-2 py-1 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activeEnrollment->schedules as $sch)
                                    <tr class="border-t {{ $sch->is_active ? '' : 'opacity-50' }}">
                                        <td class="px-2 py-1">{{ $sch->day_name }}</td>
                                        <td class="px-2 py-1 font-mono">
                                            {{ \Carbon\Carbon::parse($sch->start_time)->format('H:i') }}
                                            -
                                            {{ \Carbon\Carbon::parse($sch->end_time)->format('H:i') }}
                                        </td>
                                        <td class="px-2 py-1">
                                            {{ $sch->room ? '['.$sch->room->code.'] '.$sch->room->name : '—' }}
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            @if($sch->is_active)
                                                <span class="px-2 py-0.5 text-xs bg-green-100 text-green-800 rounded">Aktif</span>
                                            @else
                                                <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1 text-right whitespace-nowrap space-x-1">
                                            {{-- Toggle aktif --}}
                                            <form method="POST" action="{{ route('schedules.toggle-active', $sch->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs text-amber-600 hover:underline">
                                                    {{ $sch->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                            {{-- Hapus --}}
                                            <form method="POST" action="{{ route('schedules.destroy', $sch->id) }}" class="inline"
                                                  onsubmit="return confirm('Hapus jadwal ini? Hanya bisa kalau belum ada sesi ter-generate.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-red-600 hover:underline">
                                                    Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="text-xs text-gray-500 mt-2">
                            Catatan: Nonaktifkan jadwal akan menghentikan generator sesi baru,
                            tapi sesi yang sudah ada tetap. Hapus hanya bisa kalau belum ada sesi ter-generate.
                        </p>
                    @endif
                @endif

                {{-- ===== SESI MENDATANG ===== --}}
                <div class="mt-6">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="font-medium text-sm">Sesi Mendatang (5 terdekat)</h4>
                        <a href="{{ route('sessions.index', ['student_id' => $student->id]) }}"
                           class="text-xs text-blue-600 hover:underline">
                            Lihat semua sesi murid →
                        </a>
                    </div>
                    @if($upcomingSessions->isEmpty())
                        <p class="text-sm text-gray-500">
                            Belum ada sesi yang dijadwalkan. Sesi di-generate via
                            <code class="text-xs bg-gray-100 px-1">php artisan sessions:generate-month</code>
                            atau dari halaman Sesi.
                        </p>
                    @else
                        <table class="w-full text-sm border">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-1 text-left">Tanggal</th>
                                    <th class="px-2 py-1 text-left">Jam</th>
                                    <th class="px-2 py-1 text-left">Ruang</th>
                                    <th class="px-2 py-1 text-left">Guru</th>
                                    <th class="px-2 py-1 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($upcomingSessions as $s)
                                    <tr class="border-t">
                                        <td class="px-2 py-1">{{ $s->session_date->format('D, d M Y') }}</td>
                                        <td class="px-2 py-1 font-mono text-xs">
                                            {{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}
                                            -
                                            {{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}
                                        </td>
                                        <td class="px-2 py-1">{{ $s->room?->code ?? '—' }}</td>
                                        <td class="px-2 py-1">
                                            @if($s->substituteTeacher)
                                                {{ $s->substituteTeacher->name }}
                                                <span class="text-xs text-gray-500">(pengganti)</span>
                                            @else
                                                {{ $student->assignedTeacher->name ?? '—' }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <span class="px-2 py-0.5 text-xs rounded {{ $sessionStatusColors[$s->status] ?? '' }}">
                                                {{ $s->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- ============= TAGIHAN & PEMBAYARAN (M05) ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium mb-3">Tagihan & Pembayaran</h3>

                {{-- Ringkasan saldo --}}
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4 text-sm">
                    <div class="p-3 rounded {{ $outstandingBalance > 0 ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' }}">
                        <div class="text-xs uppercase text-gray-500">Saldo Outstanding</div>
                        <div class="text-2xl font-bold {{ $outstandingBalance > 0 ? 'text-red-600' : 'text-green-600' }}">
                            Rp {{ number_format($outstandingBalance, 0, ',', '.') }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $unpaidCount }} tagihan belum/sebagian lunas
                        </div>
                    </div>
                    <div class="p-3 rounded bg-blue-50 border border-blue-200">
                        <div class="text-xs uppercase text-gray-500">Total Tagihan</div>
                        <div class="text-2xl font-bold text-blue-600">
                            {{ $student->invoices->count() }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            sepanjang waktu (semua status)
                        </div>
                    </div>
                    <div class="p-3 rounded bg-gray-50 border border-gray-200 flex items-center justify-center">
                        <a href="{{ route('invoices.index', ['student_id' => $student->id]) }}"
                           class="text-blue-600 hover:underline text-sm">
                            Lihat semua tagihan →
                        </a>
                    </div>
                </div>

                {{-- 5 invoice terbaru --}}
                @if($recentInvoices->isEmpty())
                    <p class="text-sm text-gray-500">Belum ada tagihan untuk murid ini.</p>
                @else
                    @php
                        $invStatusColors = [
                            'UNPAID'  => 'bg-red-100 text-red-700',
                            'PARTIAL' => 'bg-yellow-100 text-yellow-800',
                            'PAID'    => 'bg-green-100 text-green-700',
                            'VOID'    => 'bg-gray-100 text-gray-500',
                        ];
                    @endphp
                    <table class="w-full text-sm border">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-1 text-left text-xs">No. Invoice</th>
                                <th class="px-2 py-1 text-left text-xs">Items</th>
                                <th class="px-2 py-1 text-right text-xs">Total</th>
                                <th class="px-2 py-1 text-right text-xs">Saldo</th>
                                <th class="px-2 py-1 text-center text-xs">Status</th>
                                <th class="px-2 py-1 text-left text-xs">Jatuh Tempo</th>
                                <th class="px-2 py-1 text-center text-xs">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentInvoices as $inv)
                                <tr class="border-t">
                                    <td class="px-2 py-1 font-mono text-xs">{{ $inv->invoice_number }}</td>
                                    <td class="px-2 py-1 text-xs">
                                        @foreach($inv->items as $item)
                                            <span class="inline-block px-1 bg-gray-100 rounded mr-1">{{ $item->item_code }}</span>
                                        @endforeach
                                    </td>
                                    <td class="px-2 py-1 text-right text-xs">
                                        Rp {{ number_format($inv->total_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-2 py-1 text-right text-xs font-medium
                                        {{ $inv->balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        Rp {{ number_format($inv->balance, 0, ',', '.') }}
                                    </td>
                                    <td class="px-2 py-1 text-center">
                                        <span class="px-2 py-0.5 text-xs rounded {{ $invStatusColors[$inv->status] ?? '' }}">
                                            {{ $inv->status }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-1 text-xs">
                                        {{ $inv->due_date->format('d M Y') }}
                                    </td>
                                    <td class="px-2 py-1 text-center">
                                        <a href="{{ route('invoices.show', $inv->id) }}"
                                           class="text-blue-600 hover:underline text-xs">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="text-xs text-gray-500 mt-2">
                        Menampilkan 5 tagihan terbaru.
                    </p>
                @endif
            </div>

            {{-- ============= RIWAYAT STATUS (audit trail M02) ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium mb-4">Riwayat Status</h3>

                @if($student->histories->isEmpty())
                    <p class="text-sm text-gray-500">
                        Belum ada perubahan status tercatat.
                    </p>
                @else
                    <ol class="space-y-3">
                        @foreach($student->histories as $h)
                            <li class="flex gap-3 text-sm border-l-2 pl-3
                                {{ str_contains($statusColors[$h->to_status] ?? '', 'red') ? 'border-red-300' :
                                   (str_contains($statusColors[$h->to_status] ?? '', 'green') ? 'border-green-300' :
                                   (str_contains($statusColors[$h->to_status] ?? '', 'amber') ? 'border-amber-300' :
                                   (str_contains($statusColors[$h->to_status] ?? '', 'blue') ? 'border-blue-300' :
                                   'border-gray-300'))) }}">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        @if($h->from_status)
                                            <span class="px-2 py-0.5 text-xs rounded border {{ $statusColors[$h->from_status] ?? '' }}">
                                                {{ $h->from_status }}
                                            </span>
                                            <span class="text-gray-400">→</span>
                                        @endif
                                        <span class="px-2 py-0.5 text-xs rounded border {{ $statusColors[$h->to_status] ?? '' }}">
                                            {{ $h->to_status }}
                                        </span>
                                        @if($h->skipped_trial)
                                            <span class="px-2 py-0.5 text-xs rounded bg-yellow-100 text-yellow-800 border border-yellow-300">
                                                ⚡ Skip Trial
                                            </span>
                                        @endif
                                    </div>

                                    @if($h->reason)
                                        <p class="mt-1 text-gray-700 whitespace-pre-line">{{ $h->reason }}</p>
                                    @endif

                                    @if($h->metadata)
                                        <div class="mt-1 text-xs text-gray-500 space-y-0.5">
                                            @if(!empty($h->metadata['reason_code']))
                                                <div>Kode: <code>{{ $h->metadata['reason_code'] }}</code></div>
                                            @endif
                                            @if(!empty($h->metadata['cuti_from']))
                                                <div>
                                                    Cuti: {{ \Carbon\Carbon::parse($h->metadata['cuti_from'])->format('d M Y') }}
                                                    s/d {{ \Carbon\Carbon::parse($h->metadata['cuti_until'])->format('d M Y') }}
                                                    @if(!empty($h->metadata['is_extension'])) (perpanjangan) @endif
                                                </div>
                                            @endif
                                            @if(!empty($h->metadata['trial_date']))
                                                <div>Jadwal trial: {{ \Carbon\Carbon::parse($h->metadata['trial_date'])->format('d M Y, H:i') }}</div>
                                            @endif
                                            @if(!empty($h->metadata['pending_invoices']))
                                                <div>
                                                    Tagihan menunggu:
                                                    @foreach($h->metadata['pending_invoices'] as $code)
                                                        <code class="px-1 bg-gray-100 rounded">{{ $code }}</code>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="mt-1 text-xs text-gray-400">
                                        {{ $h->created_at->format('d M Y, H:i') }}
                                        ·
                                        @if($h->changedBy)
                                            oleh {{ $h->changedBy->name }}
                                        @else
                                            sistem
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

        </div>
    </div>

    {{-- Style tambahan supaya x-cloak menyembunyikan elemen sebelum Alpine boot --}}
    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
