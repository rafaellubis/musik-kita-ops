<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Detail Murid: {{ $student->full_name }}</h2>
            <a href="{{ route('students.index') }}" class="text-sm text-gray-600 hover:underline">
                ← Kembali ke daftar
            </a>
        </div>
    </x-slot>

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
                            @php
                                $statusColors = [
                                    'Calon' => 'bg-gray-100 text-gray-700',
                                    'Trial' => 'bg-purple-100 text-purple-700',
                                    'Aktif' => 'bg-green-100 text-green-700',
                                    'Cuti' => 'bg-amber-100 text-amber-700',
                                    'Selesai' => 'bg-blue-100 text-blue-700',
                                    'Mengundurkan Diri' => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <span class="px-3 py-1 rounded text-sm font-medium {{ $statusColors[$student->status] }}">
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
                                {{-- Pakai $package->code, BUKAN $package->name --}}
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

        </div>
    </div>
</x-app-layout>