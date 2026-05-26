<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Import Murid dari Excel</h2>
                <div class="text-xs text-mk-muted mt-0.5">Migrasi data dari sistem lama</div>
            </div>
            <a href="{{ route('students.index') }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">← Kembali</a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 max-w-5xl mx-auto">

        {{-- Flash messages (semua tipe: success, error, info, warning) --}}
        @foreach(['success','error','info','warning'] as $type)
        @if(session($type))
        @php
            $flashColors = [
                'success' => 'rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)',
                'error'   => 'rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)',
                'info'    => 'rgba(96,165,250,0.1);color:#60A5FA;border:1px solid rgba(96,165,250,0.2)',
                'warning' => 'rgba(251,191,36,0.1);color:#F59E0B;border:1px solid rgba(251,191,36,0.2)',
            ];
        @endphp
        <div class="mb-4 p-3 rounded-lg text-sm" style="background:{{ $flashColors[$type] }}">
            {{ session($type) }}
        </div>
        @endif
        @endforeach

        @if(!$preview)
        {{-- ===== STEP 1: UPLOAD ===== --}}
        <div class="rounded-xl p-6 mb-6" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08)">
            <h3 class="font-semibold text-mk-text mb-4">Langkah 1 — Persiapan & Upload</h3>

            <div class="mb-5">
                <p class="text-sm text-mk-muted mb-3">
                    Download template Excel, isi data murid sesuai format di Sheet "Data Murid",
                    lalu upload kembali untuk divalidasi.
                </p>
                <a href="{{ route('import.template') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded text-sm font-semibold transition-colors"
                   style="background:rgba(93,184,144,0.15);color:#5DB890;border:1px solid rgba(93,184,144,0.3)">
                    ⬇ Download Template .xlsx
                </a>
            </div>

            <form action="{{ route('import.validate') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="border-2 border-dashed rounded-lg p-8 text-center mb-4 transition-colors"
                     style="border-color:rgba(255,255,255,0.15)"
                     x-data="{ fileName: '' }"
                     @dragover.prevent
                     @drop.prevent="
                        const file = $event.dataTransfer.files[0];
                        if (file) { fileName = file.name; $refs.fileInput.files = $event.dataTransfer.files; }
                     ">
                    <input type="file" name="file" accept=".xlsx" class="hidden" x-ref="fileInput"
                           @change="fileName = $event.target.files[0]?.name ?? ''">
                    <p class="text-mk-muted text-sm mb-3">
                        <span x-show="!fileName">Drop file .xlsx di sini atau klik tombol di bawah</span>
                        <span x-show="fileName" x-text="'📄 ' + fileName" class="text-mk-text font-medium"></span>
                    </p>
                    <button type="button" @click="$refs.fileInput.click()"
                            class="text-sm px-3 py-1.5 rounded border transition-colors"
                            style="border-color:rgba(255,255,255,0.2);color:#5DB890">
                        Pilih File
                    </button>
                    <p class="text-xs text-mk-muted mt-3">Format: .xlsx saja &nbsp;|&nbsp; Maks: 5MB</p>
                </div>

                @error('file')
                <p class="text-sm mb-3" style="color:#F87171">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full py-2.5 rounded font-bold text-sm transition-colors btn-mk-primary"
                        >
                    Validasi File
                </button>
            </form>
        </div>

        @else
        {{-- ===== STEP 2: PREVIEW ===== --}}
        @php
            $valid    = $preview['valid'];
            $overwrite= $preview['overwrite'];
            $errors   = $preview['errors'];
            $totalOk  = count($valid) + count($overwrite);
            $totalErr = count($errors);

            // Statistik jadwal untuk ringkasan
            $allRows       = array_merge($valid, $overwrite);
            $denganJadwal  = count(array_filter($allRows, fn ($r) => !empty($r['data']['preferred_day']) && ($r['data']['status'] ?? '') === 'Aktif'));
            $tanpaJadwal   = count(array_filter($allRows, fn ($r) => empty($r['data']['preferred_day'])  && ($r['data']['status'] ?? '') === 'Aktif'));
            $denganWarning = count(array_filter($allRows, fn ($r) => !empty($r['data']['_has_warning'])));
        @endphp

        <div class="rounded-xl p-6 mb-4" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08)">
            <h3 class="font-semibold text-mk-text mb-4">Langkah 2 — Preview Hasil Validasi</h3>

            {{-- Ringkasan badge --}}
            <div class="flex gap-3 mb-6 flex-wrap">
                <div class="px-4 py-2 rounded-lg text-sm font-medium"
                     style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
                    ✅ {{ count($valid) }} baru
                </div>
                <div class="px-4 py-2 rounded-lg text-sm font-medium"
                     style="background:rgba(251,191,36,0.1);color:#F59E0B;border:1px solid rgba(251,191,36,0.2)">
                    ⚠️ {{ count($overwrite) }} overwrite
                </div>
                <div class="px-4 py-2 rounded-lg text-sm font-medium"
                     style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
                    ❌ {{ $totalErr }} error (dilewati)
                </div>
            </div>

            {{-- Ringkasan jadwal: tampilkan jika ada baris yang akan diimport --}}
            @if($totalOk > 0)
            <div class="mb-5 p-3 rounded-lg text-sm" style="background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.2)">
                <p class="font-medium mb-1" style="color:#34D399">Ringkasan Jadwal</p>
                <ul class="space-y-0.5" style="color:#9CA3AF">
                    <li>✓ {{ $denganJadwal }} murid akan diimport <strong>dengan jadwal</strong></li>
                    @if($tanpaJadwal > 0)
                    <li>— {{ $tanpaJadwal }} murid tanpa jadwal (preferred_day kosong)</li>
                    @endif
                    @if($denganWarning > 0)
                    <li style="color:#FBBF24">⚠️ {{ $denganWarning }} murid dengan warning ruangan — cek setelah import</li>
                    @endif
                </ul>
            </div>
            @endif

            {{-- Tabel baris error --}}
            @if($totalErr > 0)
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-mk-muted mb-2">
                    Baris yang akan dilewati ({{ $totalErr }})
                </h4>
                <div class="overflow-x-auto rounded-lg" style="border:1px solid rgba(248,113,113,0.2)">
                    <table class="w-full text-xs">
                        <thead style="background:rgba(248,113,113,0.08)">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium" style="color:#F87171">Baris</th>
                                <th class="px-3 py-2 text-left font-medium" style="color:#F87171">Nama</th>
                                <th class="px-3 py-2 text-left font-medium" style="color:#F87171">Alasan Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($errors as $err)
                            <tr style="border-top:1px solid rgba(248,113,113,0.1)">
                                <td class="px-3 py-2 text-mk-muted">{{ $err['row'] }}</td>
                                <td class="px-3 py-2 text-mk-text">{{ $err['name'] }}</td>
                                <td class="px-3 py-2" style="color:#F87171">{{ $err['message'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Tabel baris yang akan diimport --}}
            @if($totalOk > 0)
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-mk-muted mb-2">
                    Baris yang akan diimport ({{ $totalOk }})
                </h4>
                <div class="overflow-x-auto rounded-lg" style="border:1px solid rgba(255,255,255,0.08)">
                    <div style="max-height:400px;overflow-y:auto">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0" style="background:#1A1000">
                            <tr>
                                <th class="px-3 py-2 text-left text-mk-muted font-medium">Baris</th>
                                <th class="px-3 py-2 text-left text-mk-muted font-medium">Nama</th>
                                <th class="px-3 py-2 text-left text-mk-muted font-medium">Status</th>
                                <th class="px-3 py-2 text-left text-mk-muted font-medium">Jadwal</th>
                                <th class="px-3 py-2 text-left text-mk-muted font-medium">Ruangan</th>
                                <th class="px-3 py-2 text-left text-mk-muted font-medium">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($valid as $item)
                            <tr style="border-top:1px solid rgba(255,255,255,0.05)">
                                <td class="px-3 py-2 text-mk-muted">{{ $item['row'] }}</td>
                                <td class="px-3 py-2 text-mk-text">{{ $item['data']['full_name'] }}</td>
                                <td class="px-3 py-2 text-mk-muted">{{ $item['data']['status'] }}</td>
                                <td class="px-3 py-2 text-mk-muted whitespace-nowrap">
                                    @if(!empty($item['data']['preferred_day']) && ($item['data']['status'] ?? '') === 'Aktif')
                                        @php
                                            $jadwalText = $item['data']['preferred_day'] . ' ' . $item['data']['preferred_time'];
                                            if (!empty($item['data']['_duration_min']) && !empty($item['data']['preferred_time'])) {
                                                $startParts = explode(':', $item['data']['preferred_time']);
                                                $startMins  = (int)$startParts[0] * 60 + (int)$startParts[1];
                                                $endMins    = $startMins + (int)$item['data']['_duration_min'];
                                                $endTime    = sprintf('%02d:%02d', intdiv($endMins, 60), $endMins % 60);
                                                $jadwalText = $item['data']['preferred_day'] . ' ' . $item['data']['preferred_time'] . '–' . $endTime;
                                            }
                                        @endphp
                                        {{ $jadwalText }}
                                        @if(!empty($item['data']['_has_warning']))
                                            <span class="ml-1 text-xs" style="color:#FBBF24">⚠️</span>
                                        @endif
                                    @else
                                        <span style="color:#6B7280">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-mk-muted whitespace-nowrap">
                                    @if(!empty($item['data']['_room_code']))
                                        @if(!empty($item['data']['_has_warning']))
                                            <span style="color:#FBBF24">{{ $item['data']['_room_code'] }}</span>
                                        @else
                                            {{ $item['data']['_room_code'] }}
                                        @endif
                                    @else
                                        <span style="color:#6B7280">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-medium">
                                    @if(!empty($item['data']['_conflict_warning']))
                                        <span style="color:#FB923C" title="{{ $item['data']['_conflict_warning'] }}">⚠️ Konflik jadwal</span>
                                    @elseif(!empty($item['data']['_has_warning']))
                                        <span style="color:#FBBF24" title="{{ $item['data']['_warning_message'] ?? '' }}">⚠️ Warning ruangan</span>
                                    @elseif(!empty($item['data']['preferred_day']) && ($item['data']['status'] ?? '') === 'Aktif')
                                        <span style="color:#34D399">✓ Murid + Jadwal</span>
                                    @elseif(($item['data']['status'] ?? '') === 'Aktif')
                                        <span style="color:#34D399">✓ Murid saja</span>
                                    @else
                                        <span style="color:#34D399">✓ Murid saja ({{ $item['data']['status'] }})</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            @foreach($overwrite as $item)
                            <tr style="border-top:1px solid rgba(255,255,255,0.05);background:rgba(251,191,36,0.03)">
                                <td class="px-3 py-2 text-mk-muted">{{ $item['row'] }}</td>
                                <td class="px-3 py-2 text-mk-text">{{ $item['data']['full_name'] }}</td>
                                <td class="px-3 py-2 text-mk-muted">{{ $item['data']['status'] }}</td>
                                <td class="px-3 py-2 text-mk-muted whitespace-nowrap">
                                    @if(!empty($item['data']['preferred_day']) && ($item['data']['status'] ?? '') === 'Aktif')
                                        @php
                                            $jadwalText = $item['data']['preferred_day'] . ' ' . $item['data']['preferred_time'];
                                            if (!empty($item['data']['_duration_min']) && !empty($item['data']['preferred_time'])) {
                                                $startParts = explode(':', $item['data']['preferred_time']);
                                                $startMins  = (int)$startParts[0] * 60 + (int)$startParts[1];
                                                $endMins    = $startMins + (int)$item['data']['_duration_min'];
                                                $endTime    = sprintf('%02d:%02d', intdiv($endMins, 60), $endMins % 60);
                                                $jadwalText = $item['data']['preferred_day'] . ' ' . $item['data']['preferred_time'] . '–' . $endTime;
                                            }
                                        @endphp
                                        {{ $jadwalText }}
                                        @if(!empty($item['data']['_has_warning']))
                                            <span class="ml-1 text-xs" style="color:#FBBF24">⚠️</span>
                                        @endif
                                    @else
                                        <span style="color:#6B7280">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-mk-muted whitespace-nowrap">
                                    @if(!empty($item['data']['_room_code']))
                                        @if(!empty($item['data']['_has_warning']))
                                            <span style="color:#FBBF24">{{ $item['data']['_room_code'] }}</span>
                                        @else
                                            {{ $item['data']['_room_code'] }}
                                        @endif
                                    @else
                                        <span style="color:#6B7280">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-medium">
                                    @if(!empty($item['data']['_conflict_warning']))
                                        <span style="color:#FB923C" title="{{ $item['data']['_conflict_warning'] }}">⚠️ Konflik jadwal</span>
                                    @elseif(!empty($item['data']['_has_warning']))
                                        <span style="color:#FBBF24" title="{{ $item['data']['_warning_message'] ?? '' }}">⚠️ Warning ruangan</span>
                                    @elseif(!empty($item['data']['preferred_day']) && ($item['data']['status'] ?? '') === 'Aktif')
                                        <span style="color:#F59E0B">✓ Murid + Jadwal</span>
                                    @elseif(($item['data']['status'] ?? '') === 'Aktif')
                                        <span style="color:#F59E0B">✓ Murid saja</span>
                                    @else
                                        <span style="color:#F59E0B">✓ Murid saja ({{ $item['data']['status'] }})</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Tombol aksi --}}
            <div class="flex gap-3 justify-end flex-wrap">
                <form action="{{ route('import.cancel') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="px-5 py-2 rounded text-sm border transition-colors"
                            style="border-color:rgba(255,255,255,0.2);color:#9CA3AF"
                            onclick="return confirm('Batalkan import dan hapus hasil validasi?')">
                        Batal / Upload Ulang
                    </button>
                </form>

                @if($totalOk > 0)
                <form action="{{ route('import.confirm') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="px-5 py-2 rounded font-bold text-sm transition-colors btn-mk-primary"
                            
                            onclick="return confirm('Import {{ $totalOk }} murid ke database?\n\nBaru: {{ count($valid) }}\nOverwrite: {{ count($overwrite) }}\n\nLanjutkan?')">
                        Konfirmasi Import ({{ $totalOk }} data)
                    </button>
                </form>
                @endif
            </div>
        </div>
        @endif

    </div>
</x-app-layout>
