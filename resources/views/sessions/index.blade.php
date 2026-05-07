<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Daftar Sesi</h2>
    </x-slot>

    @php
        $statusList = [
            'SCHEDULED', 'HADIR', 'HADIR_TERLAMBAT',
            'IZIN_RESCHEDULE', 'IZIN_VIDEO', 'HANGUS', 'LIBUR', 'DIGANTI',
        ];
        $statusColors = [
            'SCHEDULED'       => 'bg-gray-100 text-gray-700',
            'HADIR'           => 'bg-green-100 text-green-700',
            'HADIR_TERLAMBAT' => 'bg-yellow-100 text-yellow-800',
            'IZIN_RESCHEDULE' => 'bg-blue-100 text-blue-700',
            'IZIN_VIDEO'      => 'bg-indigo-100 text-indigo-700',
            'HANGUS'          => 'bg-red-100 text-red-700',
            'LIBUR'           => 'bg-purple-100 text-purple-700',
            'DIGANTI'         => 'bg-orange-100 text-orange-700',
        ];
        $monthName = \Carbon\Carbon::create($year, $month, 1)->format('F Y');

        // Default: bulan depan (untuk tombol generate, sesuai cron tanggal 25)
        $nextYear = (int) now()->addMonth()->year;
        $nextMonth = (int) now()->addMonth()->month;
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            {{-- ============= STATS CARDS PER STATUS ============= --}}
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2 mb-4">
                @foreach($statusList as $st)
                    <div class="p-2 rounded {{ $statusColors[$st] ?? '' }}">
                        <div class="text-xs uppercase truncate">{{ $st }}</div>
                        <div class="text-xl font-bold">{{ $stats[$st] ?? 0 }}</div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6"
                 x-data="{ showGenerate: false }">

                {{-- ============= HEADER: Period + Generate Button ============= --}}
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">
                        Sesi {{ $monthName }}
                        <span class="text-sm font-normal text-gray-500">
                            ({{ $sessions->total() }} total)
                        </span>
                    </h3>
                    @can('manage-sessions')
                    @endcan

                    @if(auth()->user()?->hasAnyRole(['Owner', 'Admin']))
                        <button type="button" @click="showGenerate = !showGenerate"
                                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded text-sm">
                            Generate Sesi Bulan
                        </button>
                    @endif
                </div>

                {{-- ===== Form Generate (toggle pakai Alpine) ===== --}}
                @if(auth()->user()?->hasAnyRole(['Owner', 'Admin']))
                    <div x-show="showGenerate" x-cloak
                         class="mb-4 p-4 border border-purple-200 bg-purple-50 rounded">
                        <form method="POST" action="{{ route('sessions.generate') }}"
                              onsubmit="return confirm('Generate sesi untuk bulan terpilih? Aksi idempotent — sesi yang sudah ada tidak duplikat.')">
                            @csrf
                            <div class="flex items-end gap-3">
                                <div>
                                    <label class="block text-xs">Tahun</label>
                                    <input type="number" name="year" required min="2024" max="2030"
                                           value="{{ $nextYear }}"
                                           class="border-gray-300 rounded text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs">Bulan (1-12)</label>
                                    <input type="number" name="month" required min="1" max="12"
                                           value="{{ $nextMonth }}"
                                           class="border-gray-300 rounded text-sm w-20">
                                </div>
                                <button type="submit"
                                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded text-sm">
                                    Jalankan Generator
                                </button>
                            </div>
                            <p class="text-xs text-gray-600 mt-2">
                                Default: bulan depan ({{ \Carbon\Carbon::create($nextYear, $nextMonth, 1)->format('F Y') }}).
                                Generator membaca semua schedule aktif, bikin sesi sesuai BR-3.3 (max 4/bulan)
                                dan BR-4.10 (libur nasional → status LIBUR otomatis).
                            </p>
                        </form>
                    </div>
                @endif

                {{-- ============= FILTER BAR ============= --}}
                <form method="GET" action="{{ route('sessions.index') }}" class="mb-4">
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-2 text-sm">
                        <div>
                            <label class="block text-xs text-gray-500">Tahun</label>
                            <input type="number" name="year" value="{{ $year }}"
                                   min="2024" max="2030"
                                   class="block w-full border-gray-300 rounded text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Bulan</label>
                            <select name="month" class="block w-full border-gray-300 rounded text-sm">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                        {{ \Carbon\Carbon::create(2026, $m, 1)->format('F') }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Guru</label>
                            <select name="teacher_id" class="block w-full border-gray-300 rounded text-sm">
                                <option value="">Semua Guru</option>
                                @foreach($teachers as $t)
                                    <option value="{{ $t->id }}"
                                        {{ request('teacher_id') == $t->id ? 'selected' : '' }}>
                                        [{{ $t->code }}] {{ $t->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Ruang</label>
                            <select name="room_id" class="block w-full border-gray-300 rounded text-sm">
                                <option value="">Semua Ruang</option>
                                @foreach($rooms as $r)
                                    <option value="{{ $r->id }}"
                                        {{ request('room_id') == $r->id ? 'selected' : '' }}>
                                        [{{ $r->code }}] {{ $r->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Murid</label>
                            <select name="student_id" class="block w-full border-gray-300 rounded text-sm">
                                <option value="">Semua Murid</option>
                                @foreach($students as $s)
                                    <option value="{{ $s->id }}"
                                        {{ request('student_id') == $s->id ? 'selected' : '' }}>
                                        {{ $s->student_code }} - {{ $s->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Status</label>
                            <select name="status" class="block w-full border-gray-300 rounded text-sm">
                                <option value="">Semua Status</option>
                                @foreach($statusList as $st)
                                    <option value="{{ $st }}"
                                        {{ request('status') == $st ? 'selected' : '' }}>{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <button type="submit" class="px-4 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                            Filter
                        </button>
                        <a href="{{ route('sessions.index') }}" class="px-4 py-1 bg-gray-200 rounded text-sm">
                            Reset
                        </a>
                    </div>
                </form>

                {{-- ============= TABLE ============= --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200 border">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-1 text-left text-xs uppercase">Tanggal</th>
                                <th class="px-2 py-1 text-left text-xs uppercase">Jam</th>
                                <th class="px-2 py-1 text-left text-xs uppercase">Murid</th>
                                <th class="px-2 py-1 text-left text-xs uppercase">Guru</th>
                                <th class="px-2 py-1 text-left text-xs uppercase">Ruang</th>
                                <th class="px-2 py-1 text-center text-xs uppercase">Status</th>
                                <th class="px-2 py-1 text-right text-xs uppercase">Honor</th>
                                <th class="px-2 py-1 text-center text-xs uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($sessions as $s)
                                <tr>
                                    <td class="px-2 py-1 whitespace-nowrap">
                                        {{ $s->session_date->format('D, d M') }}
                                    </td>
                                    <td class="px-2 py-1 font-mono text-xs whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}
                                        -
                                        {{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}
                                    </td>
                                    <td class="px-2 py-1">
                                        <a href="{{ route('students.show', $s->student_id) }}"
                                           class="text-blue-600 hover:underline">
                                            {{ $s->student->full_name ?? '?' }}
                                        </a>
                                        <span class="text-xs text-gray-500 font-mono">
                                            {{ $s->student->student_code ?? '' }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-1">
                                        @if($s->substituteTeacher)
                                            {{ $s->substituteTeacher->name }}
                                            <span class="text-xs text-orange-600">(pengganti)</span>
                                            <span class="text-xs text-gray-400 line-through ml-1">
                                                {{ $s->teacher->name ?? '' }}
                                            </span>
                                        @else
                                            {{ $s->teacher->name ?? '?' }}
                                        @endif
                                    </td>
                                    <td class="px-2 py-1 font-mono text-xs">
                                        {{ $s->room?->code ?? '—' }}
                                    </td>
                                    <td class="px-2 py-1 text-center">
                                        <span class="px-2 py-0.5 text-xs rounded {{ $statusColors[$s->status] ?? '' }}">
                                            {{ $s->status }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-1 text-right whitespace-nowrap text-xs">
                                        @if($s->honor_amount)
                                            Rp {{ number_format($s->honor_amount, 0, ',', '.') }}
                                            <div class="text-gray-400">{{ $s->honor_code }}</div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1 text-center whitespace-nowrap">
                                        @if(auth()->user()?->hasAnyRole(['Owner', 'Admin']))
                                            <a href="{{ route('attendance.edit', $s->id) }}"
                                               class="text-xs px-2 py-1 rounded
                                                      {{ $s->status === 'SCHEDULED'
                                                         ? 'bg-blue-600 hover:bg-blue-700 text-white'
                                                         : 'border border-gray-300 hover:bg-gray-50 text-gray-700' }}">
                                                {{ $s->status === 'SCHEDULED' ? 'Absen' : 'Edit' }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-2 py-6 text-center text-gray-500">
                                        Tidak ada sesi sesuai filter.
                                        @if($sessions->total() === 0 && !request()->has('teacher_id'))
                                            Coba klik "Generate Sesi Bulan" untuk men-generate sesi {{ $monthName }}.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- ============= PAGINATION ============= --}}
                <div class="mt-4">
                    {{ $sessions->links() }}
                </div>
            </div>
        </div>
    </div>

    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
