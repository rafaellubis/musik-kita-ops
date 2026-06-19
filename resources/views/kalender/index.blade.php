<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Kalender Jadwal</h2>
        <div class="text-xs text-mk-muted mt-0.5">
            Jadwal sesi minggu ini — read-only
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">

        {{-- ===== WORKSPACE UTAMA KALENDER ===== --}}
        {{-- ponytail: Menggabungkan Navigasi, Filter, dan Grid Kalender ke dalam satu panel terpadu --}}
        <div class="bg-mk-card border border-mk-border/40 shadow-sm sm:rounded-lg overflow-hidden"
             x-data="{ open: false, sesi: {} }">

            {{-- ===== COMBINED HEADER: NAVIGASI & FILTER ===== --}}
            <div class="p-4 border-b border-mk-border/20 bg-mk-surface/30 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                
                {{-- Kiri: Navigasi Minggu --}}
                <div class="flex flex-wrap items-center gap-2"
                     x-data="{
                         jumpToDate(e) {
                             const date = e.target.value;
                             if (date) {
                                 const url = new URL(window.location.href);
                                 url.searchParams.set('week', date);
                                 window.location.href = url.toString();
                             }
                         }
                     }">
                    <a href="{{ route('kalender.index', $prevWeek) }}"
                       class="px-3 py-1.5 rounded text-xs font-semibold border border-mk-border text-mk-muted hover:bg-mk-surface hover:text-mk-text transition-colors">
                        ← Minggu Lalu
                    </a>
                    
                    {{-- Tombol Tanggal Klik & Jump --}}
                    {{-- ponytail: Menggunakan datepicker bawaan browser (showPicker) tanpa memuat library calendar pihak ketiga --}}
                    <div class="relative">
                        <button type="button"
                                @click="$refs.jumpDateInput.showPicker()"
                                class="px-3 py-1.5 text-xs font-semibold text-mk-text bg-mk-surface/80 rounded border border-mk-border/50 hover:bg-mk-surface transition-colors flex items-center gap-1.5 cursor-pointer select-none"
                                title="Klik untuk pilih tanggal">
                            <span>{{ $weekStart->translatedFormat('d M') }} – {{ $weekEnd->translatedFormat('d M Y') }}</span>
                            <svg class="w-3.5 h-3.5 text-mk-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </button>
                        <input type="date"
                               x-ref="jumpDateInput"
                               @change="jumpToDate($event)"
                               class="absolute bottom-0 left-0 w-full h-0 opacity-0 pointer-events-none">
                    </div>

                    <a href="{{ route('kalender.index', $nextWeek) }}"
                       class="px-3 py-1.5 rounded text-xs font-semibold border border-mk-border text-mk-muted hover:bg-mk-surface hover:text-mk-text transition-colors">
                        Minggu Depan →
                    </a>
                    <a href="{{ route('kalender.index', $currentWeek) }}"
                       class="px-3 py-1.5 rounded text-xs font-semibold border border-mk-border text-mk-muted hover:bg-mk-surface hover:text-mk-text transition-colors ml-1">
                        Minggu Ini
                    </a>
                </div>

                {{-- Kanan: Filter --}}
                <div class="flex flex-wrap items-center gap-3">
                    <form method="GET" action="{{ route('kalender.index') }}" class="flex flex-wrap items-center gap-3">
                        {{-- Pertahankan week aktif --}}
                        <input type="hidden" name="week" value="{{ $weekStart->format('Y-m-d') }}">

                        @php
                            $teacherFilterOptions = $teachers->map(fn ($t) => [
                                'value' => $t->id,
                                'label' => $t->name,
                            ])->values()->all();

                            $roomFilterOptions = $rooms->map(fn ($r) => [
                                'value' => $r->id,
                                'label' => $r->code . ' — ' . $r->name,
                            ])->values()->all();
                        @endphp

                        {{-- Searchable Select Guru --}}
                        <div class="min-w-[160px]">
                            <x-searchable-select
                                name="teacher_id"
                                placeholder="Semua Guru"
                                :selected="request('teacher_id')"
                                :options="$teacherFilterOptions"
                                :autoSubmit="true"
                                inputClass="mk-searchable-select-trigger rounded px-3 py-2 text-xs min-w-[160px] border border-mk-border focus:ring-1 focus:ring-mk-accent focus:border-mk-accent text-mk-muted"
                            />
                        </div>

                        {{-- Searchable Select Ruangan --}}
                        {{-- ponytail: menggunakan component searchable-select agar tampilan filter konsisten antara Guru dan Ruangan --}}
                        <div class="min-w-[180px]">
                            <x-searchable-select
                                name="room_id"
                                placeholder="Semua Ruangan"
                                :selected="request('room_id')"
                                :options="$roomFilterOptions"
                                :autoSubmit="true"
                                inputClass="mk-searchable-select-trigger rounded px-3 py-2 text-xs min-w-[180px] border border-mk-border focus:ring-1 focus:ring-mk-accent focus:border-mk-accent text-mk-muted"
                            />
                        </div>

                        @if(request('teacher_id') || request('room_id'))
                            <a href="{{ route('kalender.index', ['week' => $weekStart->format('Y-m-d')]) }}"
                               class="px-3 py-2 text-xs text-mk-muted hover:text-mk-text border border-mk-border rounded hover:bg-mk-surface transition-colors flex items-center h-[34px]">
                                Reset
                            </a>
                        @endif
                    </form>
                </div>

            </div>

            {{-- ===== GRID KALENDER ===== --}}
            @php
                // Warna background per status sesi
                $statusColors = [
                    'SCHEDULED'        => 'bg-mk-surface text-mk-muted border border-mk-border/30',
                    'HADIR'            => 'bg-green-50 text-green-800 border border-green-200',
                    'HADIR_TERLAMBAT'  => 'bg-green-50 text-green-800 border border-green-200',
                    'IZIN_RESCHEDULE'  => 'bg-amber-50 text-amber-800 border border-amber-200',
                    'IZIN_VIDEO'       => 'bg-amber-50 text-amber-800 border border-amber-200',
                    'HANGUS'           => 'bg-red-50 text-red-800 border border-red-200',
                    'LIBUR'            => 'bg-purple-50 text-purple-800 border border-purple-200',
                    'DIGANTI'          => 'bg-mk-surface/40 text-mk-dim border border-mk-border/10',
                    'CANCELLED'        => 'bg-mk-surface/40 text-mk-dim border border-mk-border/10',
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

                // Warna background per ruangan
                $roomColors = [
                    'R1' => 'bg-teal-50 text-teal-800 border border-teal-200',
                    'R2' => 'bg-amber-50 text-amber-800 border border-amber-200',
                    'R3' => 'bg-sky-50 text-sky-800 border border-sky-200',
                    'R4' => 'bg-rose-50 text-rose-800 border border-rose-200',
                    'R5' => 'bg-indigo-50 text-indigo-800 border border-indigo-200',
                    'R6' => 'bg-emerald-50 text-emerald-800 border border-emerald-200',
                    'R7' => 'bg-fuchsia-50 text-fuchsia-800 border border-fuchsia-200',
                    'R8' => 'bg-orange-50 text-orange-800 border border-orange-200',
                    'R9' => 'bg-cyan-50 text-cyan-800 border border-cyan-200',
                ];
            @endphp

            @if($timeSlots->isEmpty())
                <div class="p-8 text-center border-t border-mk-border/10">
                    @if(request('teacher_id') || request('room_id'))
                        <p class="text-mk-dim text-sm">Tidak ada sesi untuk filter ini minggu ini.</p>
                    @else
                        <div class="p-4 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-700 text-sm inline-block">
                            Sesi belum di-generate untuk minggu ini.
                            Generator otomatis berjalan tanggal 25 setiap bulan.
                        </div>
                    @endif
                </div>
            @else

            {{-- Tabel grid — scroll vertikal di dalam card; baris hari sticky --}}
            <div class="kalender-grid-scroll overflow-x-auto overflow-y-auto max-h-[min(70vh,calc(100vh-14rem))]">
                <table class="w-full text-sm border-separate border-spacing-0">
                    {{-- Header: Jam + Senin–Sabtu (sticky saat scroll) --}}
                    <thead class="kalender-grid-thead">
                        <tr class="border-b">
                            <th class="kalender-grid-th-jam kalender-grid-th-sticky py-3 px-3 text-left text-xs font-semibold text-mk-dim w-16 min-w-[4rem] border-r border-b border-mk-border/40 bg-mk-surface/50">Jam</th>
                            @foreach($days as $dow => $date)
                                <th class="kalender-grid-th-day kalender-grid-th-sticky py-2.5 px-2 text-center text-xs font-semibold border-r border-b border-mk-border/40 bg-mk-surface/50
                                    {{ $date->isToday() ? 'kalender-grid-th-today text-mk-accent font-bold bg-mk-surface' : 'text-mk-muted' }}
                                    {{ $loop->last ? 'border-r-0' : '' }}">
                                    <div>{{ $date->locale('id')->translatedFormat('D') }}</div>
                                    <div class="font-normal text-xxs text-mk-dim">{{ $date->format('d/m') }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($timeSlots as $time)
                            <tr>
                                {{-- Label jam --}}
                                <td class="kalender-grid-td kalender-grid-td-jam py-2.5 px-3 text-xs text-mk-dim align-top whitespace-nowrap border-r border-b border-mk-border/20 font-mono">
                                    {{ substr($time, 0, 5) }}
                                </td>
                                {{-- Sel per hari --}}
                                @foreach($days as $dow => $date)
                                    <td class="kalender-grid-td py-2 px-2 align-top min-w-[120px] border-r border-b border-mk-border/20 {{ $loop->last ? 'border-r-0' : '' }}">
                                        @foreach($grid[$dow][$time] ?? [] as $session)
                                            @php
                                                $isCoretan  = in_array($session->status, $statusCoretan);
                                                $instrumen  = $session->enrollment->package->instrument->name ?? '?';
                                                $guruNama   = $session->teacher->name ?? '?';
                                                $roomCode   = $session->room->code ?? '?';
                                                $statusColorClass = $statusColors[$session->status] ?? 'bg-gray-100 text-gray-600 border border-gray-200';
                                                $roomColorClass = $roomColors[$roomCode] ?? 'bg-gray-100 text-gray-600 border border-gray-200';

                                                // Label singkat murid: nickname → kata pertama full_name
                                                $studentLabel = $session->student->nickname
                                                    ?: explode(' ', trim($session->student->full_name ?? ''))[0]
                                                    ?: '?';

                                                // Data untuk popup Alpine
                                                $popupData = [
                                                    'studentName'     => $session->student->full_name ?? '?',
                                                    'studentNickname' => $session->student->nickname,
                                                    'studentLabel'    => $studentLabel,
                                                    'studentCode'     => $session->student->student_code ?? '?',
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
                                                    class="w-full text-left rounded-md px-2 py-1.5 mb-1.5 text-xs transition-all hover:opacity-85 hover:scale-[1.01] shadow-sm {{ $roomColorClass }}">
                                                <div class="{{ $isCoretan ? 'line-through text-mk-dim opacity-70' : 'font-semibold text-mk-text' }} truncate">
                                                    <span>{{ $studentLabel }}</span>
                                                    <span class="text-mk-dim opacity-70 font-normal">· {{ $instrumen }}</span>
                                                </div>
                                                <div class="text-[10px] opacity-85 truncate mt-0.5 font-medium">
                                                    <span class="text-mk-muted">{{ $guruNama }}</span> · <span class="font-mono">{{ $roomCode }}</span> · {{ substr($session->start_time, 0, 5) }}
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

            {{-- ===== RIGHT SLIDE-OUT DRAWER DETAIL SESI (Alpine) ===== --}}
            {{-- ponytail: menggunakan transition dan transform standard CSS + Alpine.js ketimbang library animasi berat --}}
            <div x-show="open"
                 x-cloak
                 @click.self="open = false"
                 class="fixed inset-0 z-50 bg-black/40 flex justify-end"
                 style="display: none;">
                <div x-show="open"
                     x-transition:enter="transform transition ease-in-out duration-300"
                     x-transition:enter-start="translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transform transition ease-in-out duration-300"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="translate-x-full"
                     class="h-full w-full max-w-md bg-mk-card shadow-2xl border-l border-mk-border/30 flex flex-col"
                     @click.stop>
                    
                    {{-- Header Panel --}}
                    <div class="p-6 bg-mk-sidebar text-white flex justify-between items-start">
                        <div>
                            <span class="text-[10px] font-bold text-mk-accent uppercase tracking-wider">Detail Sesi Kalender</span>
                            <h3 class="font-bold text-lg text-white mt-1" x-text="sesi.studentName"></h3>
                            <div class="text-xs text-white/80 font-mono mt-0.5" x-text="sesi.studentCode"></div>
                            <div x-show="sesi.studentNickname" class="text-xs italic text-mk-accent mt-0.5">
                                Nickname: "<span x-text="sesi.studentNickname"></span>"
                            </div>
                        </div>
                        <button type="button"
                                @click="open = false"
                                class="text-mk-accent hover:text-white text-2xl font-bold leading-none p-1 transition-colors">
                            &times;
                        </button>
                    </div>

                    {{-- Detail Konten --}}
                    <div class="flex-1 p-6 space-y-4 overflow-y-auto">
                        <div class="grid grid-cols-2 gap-4 pb-4 border-b border-mk-border/10">
                            <div>
                                <span class="block text-xs font-semibold text-mk-muted">Status Sesi</span>
                                <span class="inline-block mt-1.5 px-2.5 py-1 rounded-md text-xs font-bold border"
                                      :class="{
                                          'bg-green-50 text-green-800 border-green-200': sesi.status === 'HADIR' || sesi.status === 'HADIR_TERLAMBAT',
                                          'bg-amber-50 text-amber-800 border-amber-200': sesi.status === 'IZIN_RESCHEDULE' || sesi.status === 'IZIN_VIDEO',
                                          'bg-red-50 text-red-800 border-red-200': sesi.status === 'HANGUS',
                                          'bg-purple-50 text-purple-800 border-purple-200': sesi.status === 'LIBUR',
                                          'bg-gray-50 text-gray-800 border-gray-200': sesi.status === 'DIGANTI' || sesi.status === 'CANCELLED',
                                          'bg-mk-surface text-mk-muted border-mk-border/30': sesi.status === 'SCHEDULED'
                                      }"
                                      x-text="sesi.statusLabel"></span>
                            </div>
                            <div>
                                <span class="block text-xs font-semibold text-mk-muted">Instrumen</span>
                                <span class="inline-block mt-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-mk-surface text-mk-sidebar border border-mk-border/30" x-text="sesi.instrumen"></span>
                            </div>
                        </div>

                        <div class="space-y-3.5">
                            <div class="flex items-center gap-3 p-3 rounded-lg bg-mk-surface/30 border border-mk-border/20">
                                <div class="p-2 rounded bg-mk-sidebar/10 text-mk-sidebar">
                                    {{-- Icon Guru --}}
                                    <svg class="w-5 h-5 text-mk-sidebar" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold text-mk-muted uppercase">GURU PENGAJAR</span>
                                    <span class="font-bold text-mk-text text-sm" x-text="sesi.teacherName"></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 p-3 rounded-lg bg-mk-surface/30 border border-mk-border/20">
                                <div class="p-2 rounded bg-mk-sidebar/10 text-mk-sidebar">
                                    {{-- Icon Ruangan --}}
                                    <svg class="w-5 h-5 text-mk-sidebar" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold text-mk-muted uppercase">RUANGAN kelas</span>
                                    <span class="font-bold text-mk-text text-sm" x-text="sesi.roomCode + ' – ' + sesi.roomName"></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 p-3 rounded-lg bg-mk-surface/30 border border-mk-border/20">
                                <div class="p-2 rounded bg-mk-sidebar/10 text-mk-sidebar">
                                    {{-- Icon Waktu --}}
                                    <svg class="w-5 h-5 text-mk-sidebar" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold text-mk-muted uppercase">WAKTU SESI</span>
                                    <span class="font-bold text-mk-text text-sm" x-text="sesi.startTime + ' – ' + sesi.endTime"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Footer Drawer / Tombol Aksi --}}
                    <div class="p-6 bg-mk-surface/30 border-t border-mk-border/20 flex gap-3">
                        <a :href="sesi.detailUrl"
                           class="flex-1 text-center px-4 py-2.5 rounded-lg text-xs font-bold border border-mk-muted/30 text-mk-muted bg-white hover:bg-mk-surface hover:border-mk-muted/60 transition-all shadow-sm">
                            Detail Murid
                        </a>
                        <a x-show="sesi.isScheduled"
                           :href="sesi.absensiUrl"
                           class="flex-1 text-center px-4 py-2.5 rounded-lg text-xs font-bold bg-mk-sidebar text-mk-accent border border-mk-sidebar hover:bg-mk-muted hover:text-white transition-all shadow-md">
                            Catat Absensi
                        </a>
                    </div>

                </div>
            </div>

        </div>

    </div>
    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
