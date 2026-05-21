<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Kalender Jadwal</h2>
        <div class="text-xs text-mk-muted mt-0.5">
            Jadwal sesi minggu ini — read-only
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 space-y-4">

        {{-- ===== WEEK NAVIGATOR ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">

                {{-- Navigasi prev / label / next --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('kalender.index', $prevWeek) }}"
                       class="px-3 py-1.5 rounded text-sm border border-gray-200 hover:bg-gray-50 transition-colors">
                        ← Minggu Lalu
                    </a>
                    <span class="px-4 py-1.5 text-sm font-semibold text-mk-text whitespace-nowrap">
                        {{ $weekStart->translatedFormat('d M') }}
                        –
                        {{ $weekEnd->translatedFormat('d M Y') }}
                    </span>
                    <a href="{{ route('kalender.index', $nextWeek) }}"
                       class="px-3 py-1.5 rounded text-sm border border-gray-200 hover:bg-gray-50 transition-colors">
                        Minggu Depan →
                    </a>
                </div>

                {{-- Tombol Minggu Ini --}}
                <a href="{{ route('kalender.index', $currentWeek) }}"
                   class="px-3 py-1.5 rounded text-sm border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">
                    Minggu Ini
                </a>

            </div>
        </div>

        {{-- ===== FILTER BAR ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <form method="GET" action="{{ route('kalender.index') }}" id="filter-form">
                {{-- Pertahankan week aktif --}}
                <input type="hidden" name="week" value="{{ $weekStart->format('Y-m-d') }}">

                <div class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Guru</label>
                        <select name="teacher_id"
                                class="border border-gray-300 rounded px-2 py-1.5 text-sm"
                                @change="$el.form.submit()">
                            <option value="">Semua Guru</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}"
                                    {{ request('teacher_id') == $t->id ? 'selected' : '' }}>
                                    {{ $t->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Ruangan</label>
                        <select name="room_id"
                                class="border border-gray-300 rounded px-2 py-1.5 text-sm"
                                @change="$el.form.submit()">
                            <option value="">Semua Ruangan</option>
                            @foreach($rooms as $r)
                                <option value="{{ $r->id }}"
                                    {{ request('room_id') == $r->id ? 'selected' : '' }}>
                                    {{ $r->code }} — {{ $r->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(request('teacher_id') || request('room_id'))
                        <a href="{{ route('kalender.index', ['week' => $weekStart->format('Y-m-d')]) }}"
                           class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded transition-colors">
                            Reset Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- ===== GRID KALENDER ===== --}}
        @php
            // Warna background per status sesi
            $statusColors = [
                'SCHEDULED'        => 'bg-gray-100 text-gray-600',
                'HADIR'            => 'bg-green-100 text-green-700',
                'HADIR_TERLAMBAT'  => 'bg-green-100 text-green-700',
                'IZIN_RESCHEDULE'  => 'bg-yellow-100 text-yellow-700',
                'IZIN_VIDEO'       => 'bg-yellow-100 text-yellow-700',
                'HANGUS'           => 'bg-red-100 text-red-700',
                'LIBUR'            => 'bg-gray-50 text-gray-400',
                'DIGANTI'          => 'bg-gray-50 text-gray-400',
                'CANCELLED'        => 'bg-gray-50 text-gray-400',
            ];
            $statusLabels = [
                'SCHEDULED'        => 'Terjadwal',
                'HADIR'            => 'Hadir',
                'HADIR_TERLAMBAT'  => 'Hadir (Terlambat)',
                'IZIN_RESCHEDULE'  => 'Izin – Reschedule',
                'IZIN_VIDEO'       => 'Izin – Video',
                'HANGUS'           => 'Hangus',
                'LIBUR'            => 'Libur',
                'DIGANTI'          => 'Diganti',
                'CANCELLED'        => 'Dibatalkan',
            ];
            $statusCoretan = ['LIBUR', 'DIGANTI', 'CANCELLED'];
        @endphp

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden"
             x-data="{ open: false, sesi: {} }">

            {{-- Pesan minggu kosong --}}
            @if($timeSlots->isEmpty())
                <div class="p-6 text-center">
                    @if(request('teacher_id') || request('room_id'))
                        <p class="text-gray-500 text-sm">Tidak ada sesi untuk filter ini minggu ini.</p>
                    @else
                        <div class="p-4 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-700 text-sm inline-block">
                            Sesi belum di-generate untuk minggu ini.
                            Generator otomatis berjalan tanggal 25 setiap bulan.
                        </div>
                    @endif
                </div>
            @else

            {{-- Tabel grid --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    {{-- Header: Jam + Senin–Sabtu --}}
                    <thead>
                        <tr class="border-b bg-gray-50">
                            <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 w-16">Jam</th>
                            @foreach($days as $dow => $date)
                                <th class="py-2 px-2 text-center text-xs font-semibold
                                    {{ $date->isToday() ? 'text-indigo-600 bg-indigo-50' : 'text-gray-600' }}">
                                    <div>{{ $date->translatedFormat('D') }}</div>
                                    <div class="font-normal text-gray-400">{{ $date->format('d/m') }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($timeSlots as $time)
                            <tr class="border-b hover:bg-gray-50/50">
                                {{-- Label jam --}}
                                <td class="py-2 px-3 text-xs text-gray-400 align-top whitespace-nowrap">
                                    {{ substr($time, 0, 5) }}
                                </td>
                                {{-- Sel per hari --}}
                                @foreach($days as $dow => $date)
                                    <td class="py-1.5 px-1.5 align-top min-w-[110px]">
                                        @foreach($grid[$dow][$time] ?? [] as $session)
                                            @php
                                                $colorClass = $statusColors[$session->status] ?? 'bg-gray-100 text-gray-600';
                                                $isCoretan  = in_array($session->status, $statusCoretan);
                                                $instrumen  = $session->enrollment->package->instrument->name ?? '?';
                                                $guruNama   = $session->teacher->name ?? '?';
                                                $roomCode   = $session->room->code ?? '?';

                                                // Data untuk popup Alpine (Task 5)
                                                $popupData = [
                                                    'studentName'  => $session->student->full_name ?? '?',
                                                    'studentCode'  => $session->student->student_code ?? '?',
                                                    'studentId'    => $session->student_id,
                                                    'teacherName'  => $guruNama,
                                                    'roomCode'     => $roomCode,
                                                    'roomName'     => $session->room->name ?? '?',
                                                    'startTime'    => substr($session->start_time, 0, 5),
                                                    'endTime'      => substr($session->end_time ?? '', 0, 5),
                                                    'status'       => $session->status,
                                                    'statusLabel'  => $statusLabels[$session->status] ?? $session->status,
                                                    'instrumen'    => $instrumen,
                                                    'isScheduled'  => $session->status === 'SCHEDULED',
                                                    'detailUrl'    => route('students.show', $session->student_id),
                                                    'absensiUrl'   => route('sessions.index'),
                                                ];
                                            @endphp
                                            <button type="button"
                                                    @click="sesi = {{ Js::from($popupData) }}; open = true"
                                                    class="w-full text-left rounded px-1.5 py-1 mb-1 text-xs
                                                           {{ $colorClass }} hover:opacity-80 transition-opacity">
                                                <div class="{{ $isCoretan ? 'line-through' : '' }} font-medium truncate">
                                                    {{ $instrumen }} · {{ $guruNama }}
                                                </div>
                                                <div class="text-xs opacity-70 truncate">
                                                    {{ $roomCode }} · {{ substr($session->start_time, 0, 5) }}
                                                </div>
                                            </button>
                                        @endforeach
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- ===== POPUP DETAIL SESI (Alpine) ===== --}}
            <div x-show="open"
                 x-cloak
                 @click.self="open = false"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-5"
                     @click.stop>

                    {{-- Header popup --}}
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="font-semibold text-gray-800" x-text="sesi.studentName"></h3>
                            <div class="text-xs text-gray-400 font-mono" x-text="sesi.studentCode"></div>
                        </div>
                        <button type="button"
                                @click="open = false"
                                class="text-gray-400 hover:text-gray-600 text-lg leading-none p-1">
                            ×
                        </button>
                    </div>

                    {{-- Detail sesi --}}
                    <div class="space-y-1.5 text-sm text-gray-600 border-t pt-3">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Instrumen</span>
                            <span class="font-medium" x-text="sesi.instrumen"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Guru</span>
                            <span x-text="sesi.teacherName"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Ruangan</span>
                            <span x-text="sesi.roomCode + ' – ' + sesi.roomName"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Jam</span>
                            <span x-text="sesi.startTime + (sesi.endTime ? ' – ' + sesi.endTime : '')"></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">Status</span>
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700"
                                  x-text="sesi.statusLabel"></span>
                        </div>
                    </div>

                    {{-- Tombol aksi --}}
                    <div class="mt-4 flex gap-2">
                        <a :href="sesi.detailUrl"
                           class="flex-1 text-center px-3 py-2 rounded text-sm border border-gray-200
                                  text-gray-600 hover:bg-gray-50 transition-colors">
                            Detail Murid
                        </a>
                        <a x-show="sesi.isScheduled"
                           :href="sesi.absensiUrl"
                           class="flex-1 text-center px-3 py-2 rounded text-sm font-medium
                                  bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                            Catat Absensi
                        </a>
                    </div>

                </div>
            </div>

        </div>

    </div>
    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
