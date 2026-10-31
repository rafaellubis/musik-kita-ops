{{-- Tab Kelas — Manajemen kelas berjalan dan riwayat kelas murid --}}
{{-- Variabel yang dibutuhkan: $student, $runningEnrollments, $historyEnrollments --}}

<div class="space-y-4">

    {{-- Notifikasi konfirmasi swap kelas utama (muncul setelah DELETE enrollment primer dengan >1 kelas aktif) --}}
    @if(session('confirm_primary_swap') && auth()->user()->hasAnyRole(['Owner', 'Admin']))
        @php $swap = session('confirm_primary_swap'); @endphp
        <div class="rounded-2xl p-4 bg-amber-50 border border-amber-200">
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
                            class="px-4 py-2 rounded-lg text-sm font-semibold bg-amber-600 hover:bg-amber-700 text-white transition-colors">
                        Hentikan &amp; Ganti Utama
                    </button>
                    <a href="{{ route('students.show', $student) }}"
                       class="px-4 py-2 text-sm text-mk-dim hover:underline">Batal</a>
                </div>
            </form>
        </div>
    @endif

    {{-- ===== KELAS BERJALAN ===== --}}
    <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden">

        {{-- Header section kelas berjalan --}}
        <div class="px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 border-b border-mk-borderLight">
            <div class="text-[10px] uppercase tracking-widest font-semibold text-secondary">
                Kelas Berjalan
                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-secondary/15 text-secondary">
                    {{ $runningEnrollments->count() }}
                </span>
            </div>
            @if(auth()->user()->hasAnyRole(['Owner', 'Admin']) && $student->status === 'Aktif')
                @php
                    $editClasses = $runningEnrollments->filter(fn ($class) =>
                        $class->status === 'ACTIVE' && $class->schedules->isNotEmpty()
                        && !$class->transfers->contains('status', 'SCHEDULED')
                        && $class->promotion?->status !== 'SCHEDULED');
                    $classAction = 'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors bg-secondary-container/30 text-secondary border border-secondary/20 hover:bg-secondary-container/50';
                @endphp
                <div x-data class="flex flex-wrap items-center gap-2">
                    <button type="button"
                            onclick="document.getElementById('modal-tambah-kelas').classList.remove('hidden')"
                            class="{{ $classAction }}">
                        + Tambah Kelas
                    </button>
                    @if($editClasses->isNotEmpty())
                        <div x-data="{ expanded: false }" class="relative"
                             @click.outside="expanded = false"
                             @keydown.escape.stop="expanded = false; $refs.editToggle.focus()">
                            <button x-ref="editToggle" type="button" @click="expanded = !expanded"
                                    :aria-expanded="expanded" aria-controls="edit-class-options"
                                    class="{{ $classAction }}">
                                Edit Kelas
                            </button>
                            <div id="edit-class-options" x-show="expanded" x-cloak
                                 class="absolute left-1/2 top-full z-20 mt-2 w-64 max-w-[calc(100vw-3rem)] -translate-x-1/2 rounded-lg border border-mk-borderLight bg-mk-card p-1 shadow-lg sm:left-auto sm:right-0 sm:translate-x-0"
                                 aria-label="Pilih kelas yang diedit">
                            @foreach($editClasses as $class)
                                @php
                                    $schedule = $class->schedules->first();
                                    $editData = [
                                        'enrollment_id' => $class->id,
                                        'source_label' => $class->package->instrument->name,
                                        'source_teacher_id' => $class->teacher_id,
                                        'source_teacher_name' => $class->teacher->name,
                                        'instrument_id' => $class->package->instrument_id,
                                        'instrument_name' => $class->package->instrument->name,
                                        'duration_min' => $class->package->duration_min,
                                        'room_id' => $schedule->room_id, 'day_of_week' => $schedule->day_of_week,
                                        'start_time' => substr($schedule->start_time, 0, 5),
                                        'effective_date' => today()->startOfMonth()->addMonth()->toDateString(),
                                        'preview_url' => route('students.enrollments.transfer-preview', [$student, $class]),
                                        'store_url' => route('students.enrollments.transfers.store', [$student, $class]),
                                    ];
                                @endphp
                                <button type="button"
                                        @click="expanded = false; $dispatch('open-transfer', @js($editData))"
                                        class="block min-h-11 w-full rounded-md px-3 py-2 text-left text-sm text-mk-text hover:bg-mk-cardHover focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary">
                                    {{ $class->package->instrument->name }} · {{ $class->teacher->name }} · {{ $class->package->code }}
                                </button>
                            @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Daftar kelas aktif --}}
        @forelse($runningEnrollments as $enrollment)
            @php
                // Ambil jadwal pertama yang aktif untuk tampilan ringkasan
                $jadwal = $enrollment->schedules->first();
                $hariMap = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                $leave = $enrollment->currentLeave;
                $leavePending = $leave && $leave->cuti_from->toDateString() > today()->toDateString();
                $leaveEnded = $leave && $leave->cuti_until->toDateString() < today()->toDateString();
            @endphp
            <div class="px-5 py-4 flex flex-wrap items-start gap-4 border-b border-mk-borderLight last:border-0">

                {{-- Ikon instrumen --}}
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base flex-shrink-0 mt-0.5 bg-secondary/10">
                    🎵
                </div>

                {{-- Informasi kelas --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-mk-text">
                            {{ $enrollment->package->instrument->name ?? '—' }}
                            &nbsp;·&nbsp;
                            <span class="font-mono">{{ $enrollment->package->code ?? '—' }}</span>
                        </span>
                        @if($enrollment->is_primary)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-secondary/15 text-secondary">
                                ★ Kelas Utama
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-mk-dim mt-1 space-y-0.5">
                        <div>
                            Guru: <span class="text-mk-muted">{{ $enrollment->teacher->name ?? '—' }}</span>
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
                                &nbsp;·&nbsp; <span class="italic text-mk-dim">Jadwal belum diatur</span>
                            @endif
                        </div>
                        <div>
                            Mulai: {{ $enrollment->effective_date ? \Carbon\Carbon::parse($enrollment->effective_date)->format('d M Y') : '—' }}
                            &nbsp;·&nbsp;
                            {{ $enrollment->package->duration_min ?? '?' }} menit/sesi
                            &nbsp;·&nbsp;
                            {{ $enrollment->package->formatted_price ?? '—' }}/bln
                        </div>
                        @if($leave)
                            <div class="pt-2 text-mk-muted">
                                <span class="font-semibold">{{ $leavePending ? 'Cuti terjadwal' : ($leaveEnded ? 'Periode cuti selesai' : 'Sedang cuti') }}:</span>
                                {{ $leave->cuti_from->format('d M Y') }}–{{ $leave->cuti_until->format('d M Y') }} (inklusif)
                            </div>
                            <div class="text-mk-muted">Aktif kembali mulai {{ $leave->cuti_until->copy()->addDay()->format('d M Y') }}. {{ $leaveEnded ? 'Periode ini sudah selesai.' : ($leave->extension_count > 0 ? 'Perpanjangan sudah digunakan.' : 'Dapat diperpanjang satu kali.') }}</div>
                        @endif
                        @foreach($enrollment->transfers as $transfer)
                            <div class="border-t border-mk-borderLight pt-3 text-sm">
                                <p class="font-semibold">{{ $transfer->sourceTeacher->name }} → {{ $transfer->targetTeacher->name }}</p>
                                <p>{{ $transfer->status === 'APPLIED' ? 'Berlaku sejak' : 'Terjadwal mulai' }} {{ $transfer->effective_date->format('d M Y') }}
                                    · {{ \App\Models\Schedule::DAY_NAMES[$transfer->after['day_of_week']] }}
                                    {{ substr($transfer->after['start_time'], 0, 5) }}–{{ substr($transfer->after['end_time'], 0, 5) }}</p>
                                <p class="whitespace-pre-line break-words text-mk-muted">{{ $transfer->reason }}</p>
                                @if($transfer->blocked_reason)
                                    <p role="alert" class="mt-2 text-red-700">Pengalihan tertahan: {{ $transfer->blocked_reason }} Perbaiki data terkait, lalu sistem mencoba kembali saat sinkronisasi.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Status badge --}}
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold flex-shrink-0 mt-1 {{ $leave && !$leavePending ? 'bg-mk-warningBg text-mk-muted' : 'bg-secondary-container/30 text-secondary' }}">
                    {{ $leave ? ($leavePending ? 'Aktif · cuti terjadwal' : ($leaveEnded ? 'Menunggu aktif kembali' : 'Cuti')) : 'Berjalan' }}
                </span>

                {{-- Aksi (hanya Owner/Admin) --}}
                @if(auth()->user()->hasAnyRole(['Owner', 'Admin']))
                    <div class="flex flex-wrap items-center gap-2 mt-0.5">
                        @php $pendingTransfer = $enrollment->transfers->firstWhere('status', 'SCHEDULED'); @endphp
                        @php
                            $promotionGrades = ['BASIC', 'L1', 'L2', 'L3', 'L4'];
                            $gradeIndex = array_search($enrollment->package?->grade, $promotionGrades, true);
                            $nextGrade = $gradeIndex !== false ? ($promotionGrades[$gradeIndex + 1] ?? null) : null;
                            $promotionTarget = $nextGrade && $enrollment->package?->class_type === 'REGULER'
                                ? $allPackages->first(fn ($p) => $p->class_type === 'REGULER' && $p->instrument_id === $enrollment->package->instrument_id && $p->grade === $nextGrade)
                                : null;
                            $promotionData = $promotionTarget ? [
                                'enrollment_id' => $enrollment->id,
                                'package_id' => $promotionTarget->id,
                                'source_label' => $enrollment->package->instrument->name.' · '.$enrollment->package->grade,
                                'target_label' => $promotionTarget->grade,
                                'instrument_id' => $enrollment->package->instrument_id,
                                'instrument_name' => $enrollment->package->instrument->name,
                                'teacher_id' => $enrollment->teacher_id,
                                'room_id' => $jadwal?->room_id,
                                'day_of_week' => $jadwal?->day_of_week,
                                'start_time' => substr($jadwal?->start_time ?? '', 0, 5),
                                'effective_date' => today()->startOfMonth()->addMonth()->toDateString(),
                                'preview_url' => route('students.enrollments.promotion-preview', [$student, $enrollment]),
                                'store_url' => route('students.enrollments.promotions.store', [$student, $enrollment]),
                            ] : null;
                        @endphp
                        @if($promotionData && !$pendingTransfer && !$enrollment->promotion && $enrollment->status === 'ACTIVE')
                            <button type="button" @click="$dispatch('open-promotion', @js($promotionData))"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors bg-mk-sidebar text-mk-accent border border-mk-sidebar hover:bg-mk-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-mk-sidebar">
                                Naik Level
                            </button>
                        @elseif($enrollment->promotion?->status === 'SCHEDULED')
                            <span class="text-sm text-mk-muted">Naik level terjadwal {{ $enrollment->promotion->effective_date->format('d M Y') }}</span>
                        @endif
                        @if($leave && in_array($student->status, ['Aktif', 'Cuti']))
                            @if($leaveEnded)
                            <form method="POST" action="{{ route('students.return-from-cuti', $student->id) }}"
                                  onsubmit="return confirm('Akhiri cuti kelas ini dan aktifkan kembali?')">
                                @csrf
                                <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                                <button type="submit" class="min-h-11 px-3 py-2 rounded-lg text-xs font-semibold bg-secondary-container/30 text-secondary border border-secondary/20 hover:bg-secondary-container/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary">
                                    Aktifkan Kembali Kelas
                                </button>
                            </form>
                            @else
                            <button type="button" disabled title="Dapat diaktifkan mulai {{ $leave->cuti_until->copy()->addDay()->format('d M Y') }}"
                                    class="min-h-11 px-3 py-2 rounded-lg text-xs font-semibold bg-mk-surface text-mk-muted border border-mk-borderLight cursor-not-allowed">
                                Aktifkan mulai {{ $leave->cuti_until->copy()->addDay()->format('d M Y') }}
                            </button>
                            @endif
                        @endif
                        {{-- Tombol "Jadikan Utama" hanya muncul jika bukan kelas utama --}}
                        @if(!$enrollment->is_primary && $enrollment->status === 'ACTIVE')
                            <form method="POST"
                                  action="{{ route('students.enrollments.set-primary', [$student, $enrollment]) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors bg-secondary-container/30 text-secondary border border-secondary/20 hover:bg-secondary-container/50">
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
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors bg-red-50 text-red-600 border border-red-200 hover:bg-red-100">
                                Hentikan
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- Panel slot sesi + tambah manual (Owner/Admin) --}}
            @if(auth()->user()->hasAnyRole(['Owner', 'Admin']) && $student->status === 'Aktif' && $enrollment->status === 'ACTIVE')
                @php $slotInfo = $enrollmentSlotSummaries[$enrollment->id] ?? null; @endphp
                @if($slotInfo)
                <div class="px-5 py-3 bg-mk-surface border-t border-mk-borderLight" x-data="{ showManualModal: false }">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-[10px] uppercase tracking-widest font-semibold text-mk-dim">
                            Slot Sesi · {{ $slotInfo['month_label'] }}
                        </div>
                        @if($slotInfo['next_sequence'])
                            <button type="button" @click="showManualModal = true"
                                    class="px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-secondary/15 text-secondary hover:bg-secondary/25 transition-colors">
                                + Tambah Sesi Manual
                            </button>
                        @else
                            <span class="text-[10px] text-mk-dim">Semua slot terisi</span>
                        @endif
                    </div>
                    <div class="flex gap-1.5">
                        @foreach([1, 2, 3, 4] as $seq)
                            @php $slotSession = $slotInfo['slots'][$seq] ?? null; @endphp
                            <div class="flex-1 text-center px-1 py-1.5 rounded text-[10px] font-medium
                                {{ $slotSession ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400' }}">
                                {{ $seq }}
                                @if($slotSession)
                                    <div class="font-normal opacity-75">
                                        {{ \Carbon\Carbon::parse($slotSession->session_date)->format('d/m') }}
                                    </div>
                                @else
                                    <div class="font-normal">—</div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($errors->has('manual_session') && old('enrollment_id') == $enrollment->id)
                        <p class="text-xs text-red-600 mt-2">{{ $errors->first('manual_session') }}</p>
                    @endif

                    <div x-show="showManualModal" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                         @click.self="showManualModal = false"
                         @keydown.escape.window="showManualModal = false">
                        <div class="bg-mk-card rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
                            <h3 class="text-base font-semibold text-mk-text mb-1">Tambah Sesi Manual</h3>
                            <p class="text-xs text-mk-dim mb-4">
                                Atribusi: {{ $slotInfo['month_label'] }} · Slot {{ $slotInfo['next_sequence'] }}
                            </p>
                            <form method="POST"
                                  action="{{ route('students.enrollments.manual-sessions.store', [$student, $enrollment]) }}">
                                @csrf
                                <input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">
                                <input type="hidden" name="session_sequence" value="{{ $slotInfo['next_sequence'] }}">
                                <div class="space-y-3 mb-4">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs font-medium text-mk-muted mb-1">Atribusi Tahun</label>
                                            <input type="number" name="attribution_year" required min="2024" max="2030"
                                                   value="{{ old('attribution_year', $slotInfo['year']) }}"
                                                   class="w-full border border-mk-border rounded-lg px-3 py-2 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-mk-muted mb-1">Atribusi Bulan</label>
                                            <select name="attribution_month" required
                                                    class="w-full border border-mk-border rounded-lg px-3 py-2 text-sm">
                                                @for($m = 1; $m <= 12; $m++)
                                                    <option value="{{ $m }}"
                                                        @selected((int) old('attribution_month', $slotInfo['month']) === $m)>
                                                        {{ \Carbon\Carbon::create(2026, $m, 1)->locale('id')->translatedFormat('F') }}
                                                    </option>
                                                @endfor
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-mk-muted mb-1">Tanggal Sesi</label>
                                        <input type="date" name="session_date" required
                                               value="{{ old('session_date') }}"
                                               class="w-full border border-mk-border rounded-lg px-3 py-2 text-sm">
                                        <p class="text-[10px] text-mk-dim mt-1">Boleh di bulan lain (rapel) — tetap masuk laporan {{ $slotInfo['month_label'] }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-mk-muted mb-1">Jam Mulai</label>
                                        <input type="time" name="start_time" required
                                               value="{{ old('start_time', $jadwal ? \Carbon\Carbon::parse($jadwal->start_time)->format('H:i') : '14:00') }}"
                                               class="w-full border border-mk-border rounded-lg px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-mk-muted mb-1">Ruangan</label>
                                        <select name="room_id" class="w-full border border-mk-border rounded-lg px-3 py-2 text-sm">
                                            <option value="">— Tanpa ruangan —</option>
                                            @foreach($allRooms ?? [] as $room)
                                                <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>
                                                    {{ $room->code }} — {{ $room->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="flex gap-2 justify-end">
                                    <button type="button" @click="showManualModal = false"
                                            class="px-4 py-2 rounded-lg text-sm text-mk-muted hover:bg-mk-surfaceHover">
                                        Batal
                                    </button>
                                    <button type="submit"
                                            class="px-4 py-2 rounded-lg text-sm font-medium bg-mk-sidebar hover:bg-opacity-90 text-white">
                                        Buat Sesi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif
            @endif
        @empty
            <div class="px-5 py-8 text-center text-sm text-mk-dim">
                Belum ada kelas berjalan.<br>
                <span class="text-xs">Klik "+ Tambah Kelas" untuk mendaftarkan kelas baru.</span>
            </div>
        @endforelse
    </div>

    @if($promotions->isNotEmpty())
        <section class="rounded-xl border border-mk-borderLight bg-mk-card p-5">
            <h3 class="font-semibold text-mk-text">Riwayat Kenaikan Level</h3>
            <div class="mt-3 divide-y divide-mk-borderLight">
                @foreach($promotions as $promotion)
                    <article class="space-y-2 py-3 text-sm">
                        <p class="font-semibold">{{ $promotion->snapshot['source_package']['code'] ?? 'Kelas asal' }} → {{ $promotion->snapshot['target_package']['code'] ?? 'Level baru' }}</p>
                        <p>{{ $promotion->status === 'SCHEDULED' ? 'Terjadwal mulai' : 'Berlaku sejak' }} {{ $promotion->effective_date->format('d M Y') }} · {{ $promotion->snapshot['schedule']['teacher'] ?? '—' }}</p>
                        <p>{{ ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][$promotion->snapshot['schedule']['day_of_week'] ?? 0] }} {{ $promotion->snapshot['schedule']['start_time'] ?? '—' }} · {{ $promotion->snapshot['schedule']['room'] ?? '—' }}</p>
                        <p class="whitespace-pre-line break-words text-mk-muted">{{ $promotion->evaluation_note }}</p>
                        <p class="text-mk-muted">Dicatat oleh {{ $promotion->creator?->name ?? '—' }} · {{ $promotion->created_at->format('d M Y H:i') }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===== RIWAYAT KELAS ===== --}}
    @if($historyEnrollments->isNotEmpty())
        <div class="bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-mk-borderLight">
                <div class="text-[10px] uppercase tracking-widest font-semibold text-secondary">
                    Riwayat Kelas
                </div>
            </div>
            @foreach($historyEnrollments as $enrollment)
                <div class="px-5 py-3.5 flex items-center gap-4 border-b border-mk-borderLight last:border-0 opacity-60">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base flex-shrink-0 bg-secondary/10">
                        🎵
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-mk-muted">
                            {{ $enrollment->package->instrument->name ?? '—' }}
                            &nbsp;·&nbsp;
                            <span class="font-mono">{{ $enrollment->package->code ?? '—' }}</span>
                        </div>
                        <div class="text-xs text-mk-dim mt-0.5">
                            Guru: {{ $enrollment->teacher->name ?? '—' }}
                            &nbsp;·&nbsp;
                            {{ $enrollment->effective_date ? \Carbon\Carbon::parse($enrollment->effective_date)->format('M Y') : '—' }}
                            –
                            {{-- Batas akhir kelas asal kenaikan bersifat eksklusif. --}}
                            {{ $enrollment->end_date ? \Carbon\Carbon::parse($enrollment->end_date)->subDays($enrollment->promotion ? 1 : 0)->format('M Y') : '—' }}
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
@if(auth()->user()->hasAnyRole(['Owner', 'Admin']))
    @include('students.partials.promotion-panel')
    @include('students.partials.transfer-panel')
@endif


{{-- ===== MODAL: TAMBAH KELAS ===== --}}
{{-- Modal ditutup dengan klik backdrop atau tombol batal/× --}}
<div id="modal-tambah-kelas"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,0.55)"
     onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-mk-card rounded-2xl shadow-2xl w-full max-w-xl max-h-[80vh] flex flex-col"
         onclick="event.stopPropagation()">

        {{-- Header modal — fixed, tidak ikut scroll --}}
        <div class="px-5 py-4 border-b border-mk-borderLight flex items-center justify-between flex-shrink-0">
            <div>
                <h4 class="font-semibold text-mk-text text-sm">Tambah Kelas</h4>
                <p class="text-xs text-mk-dim mt-0.5">{{ $student->full_name }}</p>
            </div>
            <button type="button"
                    onclick="document.getElementById('modal-tambah-kelas').classList.add('hidden')"
                    class="text-mk-dim hover:text-mk-muted text-2xl leading-none w-8 h-8 flex items-center justify-center rounded">&times;</button>
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
                    <label class="block text-xs text-mk-dim mb-1">
                        Paket <span class="text-red-400">*</span>
                    </label>
                    <select name="package_id" required
                            @change="onPackageChange($event.target.value)"
                            class="block w-full rounded-lg text-sm px-3 py-2 border border-mk-border focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
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
                    <label class="block text-xs text-mk-dim mb-1">
                        Guru <span class="text-red-400">*</span>
                    </label>
                    <select name="teacher_id" required class="block w-full rounded-lg text-sm px-3 py-2 border border-mk-border"
                            :disabled="!teachers.length">
                        <option value="">
                            <span x-show="!teachers.length">— Pilih paket dulu —</span>
                            <span x-show="teachers.length">— Pilih Guru —</span>
                        </option>
                        <template x-for="t in teachers" :key="t.id">
                            <option :value="t.id" x-text="t.name"></option>
                        </template>
                    </select>
                    <p x-show="loadingTeachers" class="text-xs text-mk-dim mt-1">Memuat guru…</p>
                </div>

                {{-- Pilih ruangan --}}
                <div>
                    <label class="block text-xs text-mk-dim mb-1">
                        Ruangan <span class="text-red-400">*</span>
                    </label>
                    <select name="room_id" required class="block w-full rounded-lg text-sm px-3 py-2 border border-mk-border">
                        <option value="">— Pilih —</option>
                        @foreach($allRooms as $room)
                            <option value="{{ $room->id }}">[{{ $room->code }}] {{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Hari jadwal --}}
                <div>
                    <label class="block text-xs text-mk-dim mb-1">
                        Hari <span class="text-red-400">*</span>
                    </label>
                    <select name="day_of_week" required class="block w-full rounded-lg text-sm px-3 py-2 border border-mk-border">
                        @foreach(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $i => $hari)
                            <option value="{{ $i }}" {{ $i === 1 ? 'selected' : '' }}>{{ $hari }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Jam mulai --}}
                <div>
                    <label class="block text-xs text-mk-dim mb-1">
                        Jam Mulai <span class="text-red-400">*</span>
                    </label>
                    <input type="time" name="start_time" value="15:00" required
                           class="block w-full rounded-lg text-sm px-3 py-2 border border-mk-border">
                </div>

                {{-- Berlaku mulai --}}
                <div>
                    <label class="block text-xs text-mk-dim mb-1">
                        Berlaku Mulai <span class="text-red-400">*</span>
                    </label>
                    <input type="date" name="effective_date"
                           value="{{ now()->addDay()->format('Y-m-d') }}" required
                           class="block w-full rounded-lg text-sm px-3 py-2 border border-mk-border">
                    <p class="text-xs text-mk-dim mt-1">
                        Tanggal sebelum hari ini? Jalankan ulang generator sesi untuk bulan terkait.
                    </p>
                </div>

                {{-- Jadikan utama (checkbox) --}}
                <div class="col-span-2 flex items-center gap-2 pt-1">
                    <input type="checkbox" name="jadikan_utama" value="1" id="modal-jadikan-utama"
                           class="rounded border-mk-border">
                    <label for="modal-jadikan-utama" class="text-sm text-mk-muted cursor-pointer">
                        Jadikan kelas utama
                        <span class="text-xs text-mk-dim">(kelas referensi SPP &amp; honor)</span>
                    </label>
                </div>

            </div>

            {{-- Footer modal — fixed, tidak ikut scroll --}}
            <div class="px-5 py-3 border-t border-mk-borderLight flex justify-end gap-2 flex-shrink-0">
                <button type="button"
                        onclick="document.getElementById('modal-tambah-kelas').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-mk-muted rounded-lg transition-colors border border-secondary/25 hover:bg-mk-surfaceHover">
                    Batal
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors bg-secondary text-white hover:bg-secondary/90">
                    Simpan &amp; Buat Jadwal
                </button>
            </div>
        </form>
    </div>
</div>
