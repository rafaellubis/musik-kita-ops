<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h2 class="font-semibold text-xl text-mk-text leading-tight">
                    Absensi Harian
                </h2>
                <div class="text-xs text-mk-dim mt-0.5">Input absensi dan jadwal pengganti harian</div>
            </div>
            {{-- Link ke Open Slot Board --}}
            @php $jumlahPending = \App\Models\ClassSession::where('status','IZIN_PENDING')->count(); @endphp
            @if($jumlahPending > 0)
            <a href="{{ route('absensi.open-slots') }}"
               class="px-4 py-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg text-sm font-semibold hover:bg-amber-100 transition-colors flex items-center gap-2 shadow-sm">
                <span>📋</span>
                <span>Open Slot Board ({{ $jumlahPending }})</span>
            </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 space-y-6">

        {{-- Tanggal + progress navigation bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-mk-card p-4 rounded-xl border border-mk-borderLight shadow-sm fade-in-up" style="animation-delay:40ms">
            {{-- Navigasi tanggal --}}
            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <a href="{{ route('absensi.index', ['date' => $tanggalObj->copy()->subDay()->toDateString()]) }}"
                   class="p-2 rounded-lg border border-mk-border hover:bg-mk-surface text-mk-muted transition-colors text-sm font-semibold"
                   title="Hari Sebelumnya">
                    &larr;
                </a>
                <span class="text-mk-text font-bold text-sm md:text-base px-2">
                    {{ $tanggalObj->translatedFormat('l, d F Y') }}
                </span>
                <a href="{{ route('absensi.index', ['date' => $tanggalObj->copy()->addDay()->toDateString()]) }}"
                   class="p-2 rounded-lg border border-mk-border hover:bg-mk-surface text-mk-muted transition-colors text-sm font-semibold"
                   title="Hari Berikutnya">
                    &rarr;
                </a>
                <form method="GET" action="{{ route('absensi.index') }}" class="ml-1">
                    <input type="date" name="date" value="{{ $tanggal }}"
                        class="border border-mk-border text-mk-muted text-sm rounded-lg px-3 py-1.5 cursor-pointer focus:border-mk-accent focus:ring-1 focus:ring-mk-accent"
                        onchange="this.form.submit()">
                </form>
            </div>

            {{-- Badge jumlah sesi --}}
            <div class="flex items-center gap-3 flex-wrap">
                @php
                    $belum   = $sessions->where('status', 'SCHEDULED')->count();
                    $selesai = $sessions->where('status', '!=', 'SCHEDULED')->count();
                @endphp
                <span class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span>
                    {{ $belum }} Belum Diinput
                </span>
                <span class="inline-flex items-center gap-1.5 bg-green-50 border border-green-200 text-green-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                    {{ $selesai }} Sudah Diinput
                </span>
            </div>
        </div>

        {{-- Filter bar --}}
        <div class="flex flex-wrap gap-3 items-center bg-mk-card p-4 rounded-xl border border-mk-borderLight shadow-sm fade-in-up" style="animation-delay:80ms">
            <div class="flex-1 min-w-[180px] relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-mk-dim text-sm">🔍</span>
                <input type="text" id="filter-murid" placeholder="Cari nama murid..."
                    class="w-full pl-9 pr-3 py-2 border border-mk-border text-mk-muted text-sm rounded-lg focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
            </div>

            <div class="flex flex-wrap gap-2">
                <select id="filter-guru"
                    class="border border-mk-border text-mk-muted text-sm rounded-lg px-3 py-2 bg-white focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                    <option value="">Semua Guru</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <select id="filter-status"
                    class="border border-mk-border text-mk-muted text-sm rounded-lg px-3 py-2 bg-white focus:border-mk-accent focus:ring-1 focus:ring-mk-accent">
                    <option value="">Semua Status Sesi</option>
                    <option value="SCHEDULED">Belum Diinput</option>
                    <option value="inputted">Sudah Diinput</option>
                </select>
            </div>
        </div>

        {{-- Tabel / empty state --}}
        @if($sessions->isEmpty())
            <div class="text-mk-dim text-sm py-12 text-center bg-mk-card rounded-2xl border border-mk-borderLight shadow-sm fade-in-up" style="animation-delay:120ms">
                🚫 Belum ada sesi terjadwal untuk tanggal ini.
            </div>
        @else
            {{-- ===== DESKTOP VIEW (md ke atas) ===== --}}
            <div class="hidden md:block bg-mk-card border border-mk-borderLight shadow-sm rounded-2xl overflow-hidden fade-in-up" style="animation-delay:120ms">
                <div class="overflow-x-auto">
                    <table class="w-full table-fixed text-sm divide-y divide-mk-border" id="tabel-absensi">
                        <thead>
                            <tr class="bg-mk-surface border-b border-mk-borderLight text-left text-[10px] font-semibold uppercase tracking-widest text-mk-dim">
                                <th class="px-4 py-3 text-left w-[10%]">Jam</th>
                                <th class="px-4 py-3 text-left w-[33%]">Murid & Paket</th>
                                <th class="px-4 py-3 text-left w-[14%]">Guru</th>
                                <th class="px-4 py-3 text-left w-[10%]">Ruang</th>
                                <th class="px-4 py-3 text-right w-[33%]">Input Kehadiran / Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-mk-borderLight">
                            @foreach($sessions as $session)
                                @include('absensi._row', ['session' => $session, 'teachers' => $teachers, 'rooms' => $rooms])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ===== MOBILE VIEW (di bawah md) ===== --}}
            <div class="block md:hidden space-y-3 fade-in-up" id="list-absensi-mobile" style="animation-delay:120ms">
                @foreach($sessions as $session)
                    @include('absensi._row-mobile', ['session' => $session, 'teachers' => $teachers, 'rooms' => $rooms])
                @endforeach
            </div>
        @endif

    </div>

    {{-- Client-side filter JS — mencakup tabel desktop DAN kartu mobile --}}
    <script>
        function applyFilter() {
            const guruId  = document.getElementById('filter-guru').value;
            const status  = document.getElementById('filter-status').value;
            const murid   = document.getElementById('filter-murid').value.toLowerCase();

            // Filter baris tabel desktop
            document.querySelectorAll('#tabel-absensi tbody tr').forEach(row => {
                const matchGuru   = !guruId || row.dataset.teacherId === guruId;
                const rowStatus   = row.dataset.status;
                const matchStatus = !status
                    || (status === 'inputted' ? rowStatus !== 'SCHEDULED' : rowStatus === status);
                const matchMurid  = !murid || row.dataset.murid.toLowerCase().includes(murid);
                row.style.display = (matchGuru && matchStatus && matchMurid) ? '' : 'none';
            });

            // Filter kartu mobile
            document.querySelectorAll('#list-absensi-mobile .card-sesi-mobile').forEach(card => {
                const matchGuru   = !guruId || card.dataset.teacherId === guruId;
                const cardStatus  = card.dataset.status;
                const matchStatus = !status
                    || (status === 'inputted' ? cardStatus !== 'SCHEDULED' : cardStatus === status);
                const matchMurid  = !murid || card.dataset.murid.toLowerCase().includes(murid);
                card.style.display = (matchGuru && matchStatus && matchMurid) ? '' : 'none';
            });
        }

        ['filter-guru', 'filter-status'].forEach(id =>
            document.getElementById(id).addEventListener('change', applyFilter)
        );
        document.getElementById('filter-murid').addEventListener('input', applyFilter);
    </script>
</x-app-layout>
