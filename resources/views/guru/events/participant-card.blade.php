{{--
    Kartu absensi murid pada event — portal guru.
    Variabel: $participant (EventParticipant), $event (Event), $assignment (EventTeacherAssignment),
              $idx (opsional, indeks urut untuk stagger animasi).

    Anatomi & bobot visual sengaja mengikuti kartu "Sesi Hari Ini" di dashboard
    (resources/views/guru/_sesi-card.blade.php): blok ikon 56px → identitas murid →
    badge status + slot aksi di kanan → detail catatan terlipat di bawah.

    Perbedaan penting dari kartu sesi: event tidak punya jam per murid, jadi blok kiri
    diisi ikon instrumen (bukan blok waktu palsu), dan gating-nya memakai aturan event
    (event terbit + sudah tanggal event + guru pendamping HADIR), bukan gating 15 menit.

    Catatan: nama konstanta enum disalin ke variabel lokal karena statement import
    tidak boleh dipakai di dalam blok PHP milik Blade (di-compile ke dalam body method).
--}}
@php
    $statusHadir = \App\Models\EventParticipant::ATTENDANCE_HADIR;
    $statusTidakHadir = \App\Models\EventParticipant::ATTENDANCE_TIDAK_HADIR;
    $assignmentHadir = \App\Models\EventTeacherAssignment::ATTENDANCE_HADIR;
    $assignmentTidakHadir = \App\Models\EventTeacherAssignment::ATTENDANCE_TIDAK_HADIR;
    $assignmentPending = \App\Models\EventTeacherAssignment::ATTENDANCE_PENDING;

    $status = $participant->attendance_status;
    $isPending = $status === \App\Models\EventParticipant::ATTENDANCE_PENDING;
    $isHadir = $status === $statusHadir;
    $isTidakHadir = $status === $statusTidakHadir;
    $isFilled = ! $isPending;

    // Alasan kartu terkunci — aturan tidak berubah, hanya presentasinya (badge + satu baris
    // penjelas, bukan blok kuning terpisah yang menambah tinggi kartu).
    $lockReasons = [];
    if ($event->isCompleted()) {
        $lockReasons[] = 'Event sudah selesai. Absensi hanya dapat dilihat.';
    }
    if ($event->isPublished() && $event->event_date->isFuture()) {
        $lockReasons[] = 'Absensi dibuka mulai tanggal event.';
    }
    if ($assignment->attendance_status === $assignmentPending) {
        $lockReasons[] = 'Menunggu konfirmasi kehadiran guru oleh Admin.';
    } elseif ($assignment->attendance_status === $assignmentTidakHadir) {
        $lockReasons[] = 'Anda tercatat tidak hadir sebagai guru pendamping.';
    }

    $canUpdate = $event->isPublished()
        && $event->event_date->lte(today())
        && $assignment->attendance_status === $assignmentHadir;

    // Badge memakai kosakata warna yang sama dengan guru/_badge-status.blade.php.
    [$badgeClass, $badgeLabel] = match($status) {
        $statusHadir => ['bg-secondary-container text-on-secondary-container', 'Hadir'],
        $statusTidakHadir => ['bg-error-container text-on-error-container', 'Tidak hadir'],
        default => ['bg-surface-container-high text-on-surface-variant', 'Belum diisi'],
    };

    // Ikon instrumen — mapping sama dengan kartu sesi dashboard supaya tidak ada dua bahasa ikon.
    $instrumentName = (string) $participant->snapshot_instrument_name;
    $instrumentIcon = match(strtolower($instrumentName)) {
        'piano' => '🎹',
        'gitar', 'guitar', 'bass' => '🎸',
        'biola', 'violin' => '🎻',
        'drum', 'drums' => '🥁',
        'vokal', 'vocal' => '🎤',
        default => '🎵',
    };

    $formId = 'participant-form-'.$participant->id;

    // Re-hidrasi isian saat validasi gagal (jalur non-JS) — hanya untuk kartu yang bersangkutan.
    $isRedisplay = (int) old('participant_id') === $participant->id;
    $selectedStatus = $isRedisplay ? old('attendance_status') : $status;
    $notesValue = $isRedisplay ? old('event_notes') : $participant->event_notes;
@endphp

<div class="bg-surface-container-lowest border border-outline-variant/20 rounded-2xl
            shadow-[rgba(44,105,78,0.04)_0px_2px_8px]
            hover:shadow-[rgba(44,105,78,0.08)_0px_4px_12px]
            transition-all duration-300
            fade-in-up"
     @if(isset($idx)) style="animation-delay: {{ ($idx + 2) * 80 }}ms" @endif
     x-data="{
         saving: false,
         error: '',
         // Simpan kehadiran tanpa reload. FormData diambil dari <form> kartu ini; status dari
         // tombol cepat ditempel manual karena tombolnya berada di luar <form> (HTML `form=`
         // attribute) sehingga tidak terdaftar sebagai successful control saat FormData dibuat.
         async save(event, status) {
             event.preventDefault();
             if (this.saving) return;
             this.saving = true;
             this.error = '';

             const form = this.$refs.form;
             const body = new FormData(form);
             if (status) body.set('attendance_status', status);

             try {
                 const response = await fetch(form.getAttribute('action'), {
                     method: 'POST',
                     body: body,
                     headers: {
                         'Accept': 'application/json',
                         'X-Requested-With': 'XMLHttpRequest'
                     }
                 });
                 const data = await response.json().catch(() => ({}));

                 if (response.ok && data.success) {
                     // Server mengirim ulang HTML kartu ini, jadi tidak ada state klien yang
                     // perlu disinkronkan manual selain hitungan kartu belum lengkap di header.
                     const wrapper = document.getElementById('participant-card-{{ $participant->id }}');
                     if (wrapper && data.card_html) wrapper.innerHTML = data.card_html;

                     const counter = document.getElementById('event-incomplete-count');
                     if (counter && typeof data.incomplete === 'number') counter.textContent = data.incomplete;
                 } else {
                     this.error = data.message || 'Gagal menyimpan kehadiran murid.';
                 }
             } catch (e) {
                 this.error = 'Gagal menyimpan kehadiran murid. Periksa koneksi lalu coba lagi.';
             } finally {
                 this.saving = false;
             }
         }
     }"
>
    <div class="flex items-center gap-4 p-4">
        {{-- Blok ikon instrumen (56px, setara blok waktu di kartu sesi) --}}
        <div class="w-14 h-14 rounded-xl flex items-center justify-center shrink-0 border text-xl
            {{ $isHadir
                ? 'bg-secondary-container/60 border-secondary/20'
                : ($isTidakHadir
                    ? 'bg-error-container/60 border-error/20'
                    : 'bg-primary/5 border-primary/10')
            }}
        ">
            <span aria-hidden="true">{{ $instrumentIcon }}</span>
            <span class="sr-only">{{ $instrumentName !== '' ? $instrumentName : 'Instrumen belum tercatat' }}</span>
        </div>

        {{-- Identitas murid --}}
        <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-primary text-base leading-snug truncate">
                {{ $participant->student->full_name }}
            </h3>
            <div class="flex flex-wrap items-center gap-1.5 mt-1">
                @if($participant->snapshot_package_code)
                    <span class="text-[10px] bg-secondary-container/40 text-secondary font-mono px-1.5 py-0.5 rounded font-medium">
                        {{ $participant->snapshot_package_code }}
                    </span>
                @endif
                @if($participant->snapshot_grade)
                    <span class="text-[11px] text-on-surface-variant font-medium">{{ $participant->snapshot_grade }}</span>
                @endif
            </div>

            @unless($canUpdate)
                <p class="text-[10px] text-on-surface-variant/80 mt-1 leading-snug">
                    {{ implode(' ', $lockReasons) ?: 'Absensi belum dapat diisi.' }}
                </p>
            @endunless

            @if($isFilled && $participant->attendance_updated_at)
                <p class="text-[10px] text-outline mt-1">
                    Diperbarui {{ $participant->attendanceUpdatedBy?->name ?? 'pengguna terhapus' }}
                    · {{ $participant->attendance_updated_at->translatedFormat('d M Y, H:i') }}
                </p>
            @endif
        </div>

        {{-- Badge status + slot aksi --}}
        <div class="flex flex-col items-end gap-2.5 shrink-0">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold {{ $badgeClass }}">
                {{ $badgeLabel }}
            </span>

            @if($canUpdate && $isPending)
                {{-- Satu ketukan, tanpa default terpilih: tidak ada tombol yang menandai hadir sendiri. --}}
                <div class="flex items-center gap-2">
                    <button type="submit" form="{{ $formId }}" name="attendance_status" value="{{ $statusHadir }}"
                            @click="save($event, '{{ $statusHadir }}')"
                            :disabled="saving"
                            title="Tandai murid hadir"
                            class="w-14 h-14 rounded-2xl font-bold bg-secondary text-on-secondary hover:bg-secondary/90 transition-all shadow-[rgba(44,105,78,0.15)_0px_2px_8px] active:scale-95 disabled:opacity-50 disabled:pointer-events-none disabled:shadow-none flex flex-col items-center justify-center gap-0.5 cursor-pointer">
                        <span class="text-base leading-none" aria-hidden="true">✓</span>
                        <span class="text-[10px] uppercase tracking-wider leading-none">Hadir</span>
                    </button>
                    <button type="submit" form="{{ $formId }}" name="attendance_status" value="{{ $statusTidakHadir }}"
                            @click="save($event, '{{ $statusTidakHadir }}')"
                            :disabled="saving"
                            title="Tandai murid tidak hadir"
                            class="w-14 h-14 rounded-2xl font-bold bg-error-container/40 text-error border border-error/40 hover:bg-error-container/70 transition-all active:scale-95 disabled:opacity-50 disabled:pointer-events-none flex flex-col items-center justify-center gap-0.5 cursor-pointer">
                        <span class="text-base leading-none" aria-hidden="true">✕</span>
                        <span class="text-[9px] uppercase tracking-wider leading-none text-center">Tidak<br>Hadir</span>
                    </button>
                </div>
            @elseif($canUpdate && $isFilled)
                {{-- Sudah terisi: ikon hasil, bukan tombol. Koreksi lewat bagian catatan di bawah. --}}
                <div role="img" aria-label="{{ $badgeLabel }}"
                     class="w-8 h-8 rounded-full flex items-center justify-center border
                        {{ $isHadir ? 'bg-secondary/10 text-secondary border-secondary/20' : 'bg-error-container/50 text-error border-error/20' }}">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        @if($isHadir)
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        @else
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/>
                        @endif
                    </svg>
                </div>
            @endif
        </div>
    </div>

    <p x-show="error" x-text="error" style="display: none;"
       class="mx-4 mb-3 rounded-xl border border-error/30 bg-error-container/50 px-3 py-2 text-xs font-semibold text-on-error-container"></p>

    @if($canUpdate)
        <form x-ref="form" id="{{ $formId }}" method="POST"
              action="{{ route('guru.events.attendance.update', $participant) }}"
              @submit.prevent="save($event)">
            @csrf
            @method('PATCH')
            <input type="hidden" name="participant_id" value="{{ $participant->id }}">

            <div class="border-t border-outline-variant/15">
                <details class="group" @if($isRedisplay) open @endif>
                    <summary class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                        <span class="text-sm font-semibold text-primary shrink-0 flex items-center gap-1.5">
                            Catatan event
                            @if($isPending)
                                <span class="text-[#2c694e] font-normal text-xs hidden sm:inline">— opsional</span>
                            @endif
                        </span>
                        @if($participant->event_notes && ! $isRedisplay)
                            {{-- Preview satu baris supaya catatan lama tetap terbaca tanpa membuka lipatan. --}}
                            <span class="flex-1 min-w-0 truncate text-right text-xs text-on-surface-variant">
                                {{ \Illuminate\Support\Str::limit($participant->event_notes, 80) }}
                            </span>
                        @else
                            <span class="flex-1"></span>
                        @endif
                        <span class="text-xs text-outline shrink-0 group-open:hidden">▼</span>
                        <span class="text-xs text-outline shrink-0 hidden group-open:inline">▲</span>
                    </summary>

                    <div class="space-y-3 px-4 pb-4">
                        <label for="event-notes-{{ $participant->id }}" class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider">
                            Catatan khusus penampilan murid
                        </label>
                        <textarea id="event-notes-{{ $participant->id }}" name="event_notes" rows="3" maxlength="1000"
                                  class="w-full border border-outline-variant/35 rounded-xl px-3 py-2 text-sm bg-white text-on-surface placeholder:text-on-surface-variant focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary resize-y"
                                  placeholder="Contoh: tempo lagu kedua perlu diperlambat">{{ $notesValue }}</textarea>
                        @if($isRedisplay)
                            @error('event_notes')
                                <p class="text-xs font-semibold text-error">{{ $message }}</p>
                            @enderror
                        @endif

                        @if($isFilled)
                            <fieldset>
                                <legend class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Ubah kehadiran</legend>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach([$statusHadir => 'Hadir', $statusTidakHadir => 'Tidak hadir'] as $value => $label)
                                        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-outline-variant/35 px-3 text-sm font-semibold text-on-surface has-[:checked]:border-secondary has-[:checked]:bg-secondary/10">
                                            <input type="radio" name="attendance_status" value="{{ $value }}"
                                                   @checked($selectedStatus === $value)
                                                   class="border-outline text-secondary focus:ring-secondary">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                @if($isRedisplay)
                                    @error('attendance_status')
                                        <p class="mt-1 text-xs font-semibold text-error">{{ $message }}</p>
                                    @enderror
                                @endif
                            </fieldset>

                            <button type="submit" :disabled="saving"
                                    class="w-full rounded-xl bg-primary py-2.5 text-sm font-bold text-on-primary transition-colors hover:bg-primary-container disabled:opacity-50 cursor-pointer">
                                Simpan Kehadiran
                            </button>
                        @else
                            <p class="text-xs text-on-surface-variant">
                                Pilih <span class="font-semibold text-primary">Hadir</span> atau
                                <span class="font-semibold text-primary">Tidak hadir</span> lewat tombol di atas —
                                catatan ini tersimpan bersamaan.
                            </p>
                        @endif
                    </div>
                </details>
            </div>
        </form>
    @elseif($participant->event_notes)
        <div class="border-t border-outline-variant/15 px-4 py-3">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant">Catatan event</p>
            <p class="mt-1 whitespace-pre-line text-sm text-on-surface">{{ $participant->event_notes }}</p>
        </div>
    @endif
</div>
