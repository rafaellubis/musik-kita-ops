<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text leading-tight">
            Absensi Harian
        </h2>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">

            {{-- Tanggal + progress --}}
            <div class="flex items-center gap-3 mb-4 flex-wrap">
                <span class="text-mk-dim text-sm">
                    {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
                </span>
                <form method="GET" action="{{ route('absensi.index') }}">
                    <input type="date" name="date" value="{{ $tanggal }}"
                        class="border border-mk-border text-mk-muted text-sm rounded px-3 py-1.5 cursor-pointer"
                        onchange="this.form.submit()">
                </form>
                @php
                    $belum   = $sessions->where('status', 'SCHEDULED')->count();
                    $selesai = $sessions->where('status', '!=', 'SCHEDULED')->count();
                @endphp
                <span class="bg-yellow-100 text-yellow-700 text-xs font-semibold px-3 py-1 rounded-full">
                    {{ $belum }} belum diinput
                </span>
                <span class="bg-green-100 text-green-700 text-xs px-3 py-1 rounded-full">
                    {{ $selesai }} sudah ✓
                </span>
            </div>

            {{-- Filter bar --}}
            <div class="flex items-center gap-2 mb-3 flex-wrap">
                <select id="filter-guru"
                    class="border border-mk-border text-mk-muted text-sm rounded px-3 py-1.5">
                    <option value="">Semua Guru</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <select id="filter-status"
                    class="border border-mk-border text-mk-muted text-sm rounded px-3 py-1.5">
                    <option value="">Semua Status</option>
                    <option value="SCHEDULED">Belum Diinput</option>
                    <option value="inputted">Sudah Diinput</option>
                </select>
                <input type="text" id="filter-murid" placeholder="Cari murid..."
                    class="border border-mk-border text-mk-muted text-sm rounded px-3 py-1.5 w-48">
            </div>

            {{-- Tabel / empty state --}}
            @if($sessions->isEmpty())
                <div class="text-mk-dim text-sm py-12 text-center bg-mk-card rounded-lg shadow-sm border border-mk-border">
                    Belum ada sesi terjadwal untuk tanggal ini.
                </div>
            @else
                <div class="bg-mk-card shadow-sm rounded-lg">
                    <table class="w-full table-fixed text-sm divide-y divide-mk-border" id="tabel-absensi">
                        <thead class="bg-mk-surface rounded-t-lg">
                            <tr class="text-mk-dim font-medium text-xs uppercase tracking-wider">
                                <th class="px-3 py-3 text-left w-[8%] rounded-tl-lg">Jam</th>
                                <th class="px-3 py-3 text-left w-[35%]">Murid</th>
                                <th class="px-3 py-3 text-center w-[14%]">Guru</th>
                                <th class="px-3 py-3 text-left w-[8%]">Ruang</th>
                                <th class="px-3 py-3 text-right w-[35%] rounded-tr-lg">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-mk-border">
                            @foreach($sessions as $session)
                                @include('absensi._row', ['session' => $session, 'teachers' => $teachers, 'rooms' => $rooms])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

    </div>

    {{-- Client-side filter JS --}}
    <script>
        function applyFilter() {
            const guruId  = document.getElementById('filter-guru').value;
            const status  = document.getElementById('filter-status').value;
            const murid   = document.getElementById('filter-murid').value.toLowerCase();

            document.querySelectorAll('#tabel-absensi tbody tr').forEach(row => {
                const matchGuru   = !guruId || row.dataset.teacherId === guruId;
                const rowStatus   = row.dataset.status;
                const matchStatus = !status
                    || (status === 'inputted' ? rowStatus !== 'SCHEDULED' : rowStatus === status);
                const matchMurid  = !murid || row.dataset.murid.toLowerCase().includes(murid);
                row.style.display = (matchGuru && matchStatus && matchMurid) ? '' : 'none';
            });
        }

        ['filter-guru', 'filter-status'].forEach(id =>
            document.getElementById(id).addEventListener('change', applyFilter)
        );
        document.getElementById('filter-murid').addEventListener('input', applyFilter);
    </script>
</x-app-layout>
