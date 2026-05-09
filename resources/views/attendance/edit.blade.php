<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Input Absensi</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $session->session_date->format('D, d M Y') }} ·
                    {{ $session->student->full_name ?? '?' }}
                </div>
            </div>
            <a href="{{ route('sessions.index', ['year' => $session->session_date->year, 'month' => $session->session_date->month]) }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    @php
        $package = $session->enrollment?->package;
        $isKids = $package && $package->isKidsClass();
        $baseHonor = $package
            ? (int) round($package->price_per_month * 0.5 / 4)
            : 0;

        // Estimasi honor per status (untuk tooltip — kalkulasi server otoritatif)
        $estimates = $isKids ? [
            'HADIR'           => 42500,  'HADIR_TERLAMBAT' => 42500,
            'IZIN_RESCHEDULE' => 0,      'IZIN_VIDEO'      => 42500,
            'HANGUS'          => 42500,  'LIBUR'           => 42500,
            'DIGANTI'         => 42500,
        ] : [
            'HADIR'           => $baseHonor, 'HADIR_TERLAMBAT' => $baseHonor,
            'IZIN_RESCHEDULE' => 0,          'IZIN_VIDEO'      => $baseHonor,
            'HANGUS'          => $baseHonor, 'LIBUR'           => $baseHonor,
            'DIGANTI'         => $baseHonor,
        ];

        $statusDescriptions = [
            'HADIR'           => 'Murid hadir tepat waktu.',
            'HADIR_TERLAMBAT' => 'Murid hadir tapi terlambat (isi menit).',
            'IZIN_RESCHEDULE' => 'Izin pertama bulan ini, info ≥5 jam (BR-4.4). Honor di sesi pengganti.',
            'IZIN_VIDEO'      => 'Izin ke-2+ bulan ini → guru kerjakan video (BR-4.6).',
            'HANGUS'          => 'Murid no-show / info <5 jam. Sesi dianggap masuk (BR-4.7).',
            'LIBUR'           => 'Libur nasional. Honor guru tetap dibayar (BR-4.10).',
            'DIGANTI'         => 'Diajar guru pengganti. Honor → guru pengganti (BR-4.9).',
        ];
    @endphp

    <div class="py-6 px-4 lg:px-8 space-y-4">

        @if(session('error'))
        <div class="p-3 rounded-lg text-sm"
             style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
            {{ session('error') }}
        </div>
        @endif
        @if($errors->any())
        <div class="p-3 rounded-lg text-sm"
             style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- ============= CONTEXT SESI ============= --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Info Sesi</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-gray-500">Tanggal</dt>
                    <dd class="font-medium">{{ $session->session_date->format('l, d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Jam</dt>
                    <dd class="font-mono">
                        {{ \Carbon\Carbon::parse($session->start_time)->format('H:i') }}
                        -
                        {{ \Carbon\Carbon::parse($session->end_time)->format('H:i') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Murid</dt>
                    <dd>
                        <a href="{{ route('students.show', $session->student_id) }}"
                           class="text-blue-600 hover:underline">
                            {{ $session->student->full_name ?? '?' }}
                        </a>
                        <span class="text-xs text-gray-500 font-mono">
                            {{ $session->student->student_code ?? '' }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Guru Asli</dt>
                    <dd>{{ $session->teacher->name ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Paket</dt>
                    <dd>
                        @if($package)
                            <span class="font-mono">{{ $package->code }}</span>
                            · {{ $package->instrument->name ?? '' }}
                            @if($isKids)
                                <span class="ml-1 text-xs px-1 bg-blue-100 text-blue-800 rounded">Kids Class</span>
                            @endif
                        @else
                            <span class="text-gray-400">— (sesi ad-hoc / trial)</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Ruang</dt>
                    <dd>{{ $session->room ? '['.$session->room->code.'] '.$session->room->name : '—' }}</dd>
                </div>
                @if($session->status !== 'SCHEDULED')
                    <div class="md:col-span-2">
                        <dt class="text-gray-500">Status sebelumnya</dt>
                        <dd class="font-medium text-amber-700">
                            {{ $session->status }}
                            @if($session->honor_amount)
                                · honor Rp {{ number_format($session->honor_amount, 0, ',', '.') }}
                            @endif
                        </dd>
                        <p class="text-xs text-gray-500 mt-1">
                            Submit form akan menggantikan absensi sebelumnya.
                        </p>
                    </div>
                @endif
            </dl>
        </div>

        {{-- ============= FORM ABSENSI ============= --}}
        <form method="POST" action="{{ route('attendance.update', $session->id) }}"
              x-data="{ status: '{{ old('status', $session->status === 'SCHEDULED' ? '' : $session->status) }}' }">
            @csrf
            @method('PATCH')

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Pilih Status Absensi</h3>

                {{-- Radio buttons untuk 7 status --}}
                <div class="space-y-2">
                    @foreach($statusDescriptions as $st => $desc)
                        <label class="flex items-start gap-3 p-3 border rounded cursor-pointer hover:bg-gray-50"
                               :class="status === '{{ $st }}' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                            <input type="radio" name="status" value="{{ $st }}" required
                                   x-model="status"
                                   class="mt-1">
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ $st }}</span>
                                    <span class="text-xs text-gray-500">
                                        Honor: Rp {{ number_format($estimates[$st], 0, ',', '.') }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600 mt-1">{{ $desc }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>

                {{-- Field conditional: late_minutes (hanya kalau HADIR_TERLAMBAT) --}}
                <div x-show="status === 'HADIR_TERLAMBAT'" x-cloak
                     class="p-3 border border-yellow-200 bg-yellow-50 rounded">
                    <label class="block text-sm font-medium">
                        Menit Terlambat <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="late_minutes"
                           min="1" max="60"
                           value="{{ old('late_minutes', $session->late_minutes) }}"
                           class="mt-1 block w-32 border-gray-300 rounded text-sm">
                    <p class="text-xs text-gray-500 mt-1">Range 1-60 menit.</p>
                </div>

                {{-- Field conditional: substitute_teacher (hanya kalau DIGANTI) --}}
                <div x-show="status === 'DIGANTI'" x-cloak
                     class="p-3 border border-orange-200 bg-orange-50 rounded">
                    <label class="block text-sm font-medium">
                        Guru Pengganti <span class="text-red-500">*</span>
                    </label>
                    <select name="substitute_teacher_id"
                            class="mt-1 block w-full border-gray-300 rounded text-sm">
                        <option value="">— Pilih guru pengganti —</option>
                        @foreach($substituteCandidates as $t)
                            <option value="{{ $t->id }}"
                                {{ old('substitute_teacher_id', $session->substitute_teacher_id) == $t->id ? 'selected' : '' }}>
                                [{{ $t->code }}] {{ $t->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        Hanya guru aktif yang mengajar instrumen sama (matriks). Honor otomatis ke pengganti (BR-4.9).
                    </p>
                </div>

                {{-- Catatan opsional --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Catatan Internal</label>
                    <textarea name="notes" rows="2" maxlength="500"
                              class="mt-1 block w-full border-gray-300 rounded text-sm"
                              placeholder="Mis: ortu kabar via WA, murid demam, dll.">{{ old('notes', $session->notes) }}</textarea>
                </div>

                {{-- Submit --}}
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <a href="{{ route('sessions.index', ['year' => $session->session_date->year, 'month' => $session->session_date->month]) }}"
                       class="px-4 py-2 bg-gray-200 rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-bold transition-colors"
                            style="background:#D4A853;color:#1A1000">
                        Simpan Absensi
                    </button>
                </div>
            </div>
        </form>
    </div>

    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
