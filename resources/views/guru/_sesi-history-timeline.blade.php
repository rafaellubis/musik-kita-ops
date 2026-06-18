@if($history->isEmpty())
    <div class="flex flex-col items-center justify-center py-12 text-center">
        <div class="w-16 h-16 bg-surface rounded-full flex items-center justify-center border border-border-light text-2xl mb-3">
            📅
        </div>
        <h4 class="font-semibold text-primary text-sm">Belum ada riwayat sesi</h4>
        <p class="text-xs text-on-surface-variant/70 mt-1 max-w-[240px]">
            Belum ada riwayat sesi yang tercatat hadir pada bulan berjalan ini.
        </p>
    </div>
@else
    <div class="relative pl-6 border-l-2 border-outline-variant/30 ml-2.5 mt-2">
        @foreach($history as $idx => $sesi)
            @php
                $note = $sesi->teacherNote;
                $dateObj = \Carbon\Carbon::parse($sesi->session_date);
            @endphp
            <div class="relative mb-6 last:mb-2">
                <!-- Penanda titik timeline -->
                <div class="absolute -left-[31px] top-1.5 w-3 h-3 rounded-full bg-accent border-2 border-white ring-2 ring-accent/30"></div>
                
                <!-- Header metadata sesi -->
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="text-xs font-bold text-primary">
                        {{ $dateObj->locale('id')->isoFormat('dddd, D MMM Y') }}
                    </span>
                    <span class="text-[9px] bg-secondary-container/40 text-secondary font-semibold px-2 py-0.5 rounded-full border border-secondary/20 uppercase tracking-wide">
                        {{ $sesi->status }}
                    </span>
                </div>

                <!-- Kartu Rincian Catatan -->
                <div class="bg-surface/50 border border-outline-variant/20 rounded-xl p-3 shadow-sm">
                    <!-- Rating Bintang -->
                    @if($note && $note->session_rating)
                        <div class="text-amber-500 text-xs mb-1.5 flex gap-0.5" title="Rating: {{ $note->session_rating }}/5">
                            @for($i = 1; $i <= 5; $i++)
                                <span>{{ $i <= $note->session_rating ? '★' : '☆' }}</span>
                            @endfor
                        </div>
                    @endif

                    <!-- Kolom Konten Catatan -->
                    <div class="space-y-2">
                        <div>
                            <span class="block text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-wider">Materi</span>
                            <p class="text-xs text-on-surface mt-0.5 leading-relaxed">
                                {{ $note && $note->material_learned ? $note->material_learned : '—' }}
                            </p>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-wider">Tugas &amp; Latihan</span>
                            <p class="text-xs text-on-surface mt-0.5 leading-relaxed">
                                {{ $note && $note->homework_notes ? $note->homework_notes : '—' }}
                            </p>
                        </div>
                        @if($note && $note->notes)
                            <div>
                                <span class="block text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-wider">Catatan Tambahan</span>
                                <p class="text-xs text-on-surface-variant mt-0.5 leading-relaxed italic">
                                    "{{ $note->notes }}"
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
