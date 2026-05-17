<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-amber-400 leading-tight">
            Absensi Harian
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Tanggal + progress --}}
            <div class="flex items-center gap-3 mb-4 flex-wrap">
                <span class="text-gray-400 text-sm">
                    {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
                </span>
                <form method="GET" action="{{ route('admin.absensi.index') }}">
                    <input type="date" name="date" value="{{ $tanggal }}"
                        class="bg-stone-800 border border-stone-600 text-gray-200 text-sm rounded px-3 py-1.5 cursor-pointer"
                        onchange="this.form.submit()">
                </form>
                @php
                    $belum   = $sessions->where('status', 'SCHEDULED')->count();
                    $selesai = $sessions->where('status', '!=', 'SCHEDULED')->count();
                @endphp
                <span class="bg-amber-400/10 text-amber-400 text-xs font-semibold px-3 py-1 rounded-full">
                    {{ $belum }} belum diinput
                </span>
                <span class="bg-emerald-500/10 text-emerald-400 text-xs px-3 py-1 rounded-full">
                    {{ $selesai }} sudah ✓
                </span>
            </div>

            {{-- Filter bar --}}
            <div class="flex items-center gap-2 mb-3 flex-wrap">
                <select id="filter-guru"
                    class="bg-stone-800 border border-stone-600 text-amber-400 text-sm rounded px-3 py-1.5">
                    <option value="">Semua Guru</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <select id="filter-status"
                    class="bg-stone-800 border border-stone-600 text-amber-400 text-sm rounded px-3 py-1.5">
                    <option value="">Semua Status</option>
                    <option value="SCHEDULED">Belum Diinput</option>
                    <option value="inputted">Sudah Diinput</option>
                </select>
                <input type="text" id="filter-murid" placeholder="Cari murid..."
                    class="bg-stone-800 border border-stone-600 text-gray-300 text-sm rounded px-3 py-1.5 w-48">
            </div>

            {{-- Tabel / empty state --}}
            @if($sessions->isEmpty())
                <div class="text-gray-500 text-sm py-12 text-center bg-stone-900 rounded-xl border border-stone-700">
                    Belum ada sesi terjadwal untuk tanggal ini.
                </div>
            @else
                <div class="bg-stone-900 border border-stone-700 rounded-xl overflow-hidden">
                    <table class="w-full text-sm" id="tabel-absensi">
                        <thead class="bg-stone-800/50">
                            <tr class="text-gray-500 font-medium text-xs uppercase tracking-wide">
                                <th class="px-4 py-3 text-left w-16">Jam</th>
                                <th class="px-3 py-3 text-left">Murid</th>
                                <th class="px-3 py-3 text-left w-28">Guru</th>
                                <th class="px-3 py-3 text-left w-16">Ruang</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessions as $session)
                                @include('admin.absensi._row', ['session' => $session, 'teachers' => $teachers])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
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
