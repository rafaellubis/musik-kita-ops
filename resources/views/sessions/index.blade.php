<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-mk-text">Daftar Jadwal</h2>
            <div class="text-xs text-mk-muted mt-0.5">
                @php $monthName = \Carbon\Carbon::create($year, $month, 1)->format('F Y'); @endphp
                {{ $monthName }} · {{ $sessions->total() }} sesi
            </div>
        </div>
    </x-slot>

    @php
        $statusList = [
            'SCHEDULED', 'HADIR', 'HADIR_TERLAMBAT',
            'IZIN_RESCHEDULE', 'IZIN_VIDEO', 'HANGUS', 'LIBUR', 'DIGANTI', 'CANCELLED',
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
            'CANCELLED'       => 'bg-gray-200 text-gray-500 line-through',
        ];
        $nextYear  = (int) now()->addMonth()->year;
        $nextMonth = (int) now()->addMonth()->month;
    @endphp

    <div class="py-6 px-4 lg:px-8">

        @if(session('conflict_warnings'))
        <div class="mb-5 p-3 rounded-lg text-sm font-medium"
             style="background:rgba(251,191,36,0.14);color:#FCD34D;border:1px solid rgba(251,191,36,0.35)">
            <div class="font-semibold mb-1">⚠ {{ count(session('conflict_warnings')) }} sesi terskip karena konflik jadwal — atur ulang jadwal murid berikut secara manual:</div>
            <ul class="mt-1 space-y-0.5 list-disc list-inside">
                @foreach(session('conflict_warnings') as $w)
                <li>{{ $w }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700">
            @foreach($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
        @endif

        {{-- ===== STATS CARDS PER STATUS ===== --}}
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2 mb-5">
            @foreach($statusList as $st)
                <div class="p-2.5 rounded-lg {{ $statusColors[$st] ?? '' }}">
                    <div class="text-[10px] uppercase font-semibold tracking-wide truncate">{{ $st }}</div>
                    <div class="text-xl font-bold mt-0.5">{{ $stats[$st] ?? 0 }}</div>
                </div>
            @endforeach
        </div>

        <div class="bg-mk-card shadow-sm sm:rounded-lg p-5"
             x-data="{ showGenerate: false, editSession: null }">

            @php $canEdit = auth()->user()?->hasAnyRole(['Owner', 'Admin']); @endphp

            {{-- ===== HEADER: Period + Generate Button ===== --}}
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-semibold text-mk-muted">Sesi {{ $monthName }}</h3>
                @if($canEdit)
                    <div class="flex items-center gap-2">
                        <a href="{{ route('absensi.index') }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors btn-mk-primary"
                           >
                            Absensi Hari Ini
                        </a>
                        <button type="button" @click="showGenerate = !showGenerate"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors btn-mk-primary"
                                >
                            Generate Sesi Bulan
                        </button>
                    </div>
                @endif
            </div>

            {{-- ===== Form Generate (toggle pakai Alpine) ===== --}}
            @if($canEdit)
                <div x-show="showGenerate" x-cloak
                     class="mb-4 p-4 border border-mk-border bg-mk-surface rounded-lg">
                    <form method="POST" action="{{ route('sessions.generate') }}"
                          onsubmit="return confirm('Generate sesi untuk bulan terpilih? Idempotent — sesi yang sudah ada tidak duplikat.')">
                        @csrf
                        <div class="flex items-end gap-3 flex-wrap">
                            <div>
                                <label class="block text-xs text-mk-dim mb-1">Tahun</label>
                                <input type="number" name="year" required min="2024" max="2030"
                                       value="{{ $nextYear }}"
                                       class="border-mk-border rounded text-sm w-24">
                            </div>
                            <div>
                                <label class="block text-xs text-mk-dim mb-1">Bulan (1-12)</label>
                                <input type="number" name="month" required min="1" max="12"
                                       value="{{ $nextMonth }}"
                                       class="border-mk-border rounded text-sm w-20">
                            </div>
                            <button type="submit"
                                    class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary"
                                    >
                                Jalankan Generator
                            </button>
                        </div>
                        <p class="text-xs text-mk-dim mt-2">
                            Default: bulan depan ({{ \Carbon\Carbon::create($nextYear, $nextMonth, 1)->format('F Y') }}).
                            Generator membaca semua schedule aktif, buat sesi sesuai BR-3.3 (max 4/bulan)
                            dan BR-4.10 (libur nasional → status LIBUR otomatis).
                        </p>
                    </form>
                </div>
            @endif

            {{-- ===== FILTER BAR ===== --}}
            <form method="GET" action="{{ route('sessions.index') }}" class="mb-4">
                <div class="grid grid-cols-2 md:grid-cols-6 gap-2 text-sm">
                    <div>
                        <label class="block text-xs text-mk-dim mb-1">Tahun</label>
                        <input type="number" name="year" value="{{ $year }}" min="2024" max="2030"
                               class="block w-full border-mk-border rounded text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-mk-dim mb-1">Bulan</label>
                        <select name="month" class="block w-full border-mk-border rounded text-sm">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(2026, $m, 1)->format('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    @php
                        $teacherFilterOptions = $teachers->map(fn ($t) => [
                            'value' => $t->id,
                            'label' => '['.$t->code.'] '.$t->name,
                        ])->values()->all();
                        $studentFilterOptions = $students->map(fn ($s) => [
                            'value' => $s->id,
                            'label' => $s->student_code.' - '.$s->full_name,
                        ])->values()->all();
                    @endphp
                    <div>
                        <x-searchable-select
                            name="teacher_id"
                            label="Guru"
                            placeholder="Semua Guru"
                            :selected="request('teacher_id')"
                            :options="$teacherFilterOptions"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-mk-dim mb-1">Ruang</label>
                        <select name="room_id" class="block w-full border-mk-border rounded text-sm">
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
                        <x-searchable-select
                            name="student_id"
                            label="Murid"
                            placeholder="Semua Murid"
                            :selected="request('student_id')"
                            :options="$studentFilterOptions"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-mk-dim mb-1">Status</label>
                        <select name="status" class="block w-full border-mk-border rounded text-sm">
                            <option value="">Semua Status</option>
                            @foreach($statusList as $st)
                                <option value="{{ $st }}"
                                    {{ request('status') == $st ? 'selected' : '' }}>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-2 flex gap-2">
                    <button type="submit"
                            class="px-4 py-1.5 rounded-lg text-xs font-bold transition-colors btn-mk-primary"
                            >
                        Filter
                    </button>
                    <a href="{{ route('sessions.index') }}"
                       class="px-4 py-1.5 bg-mk-surface rounded-lg text-xs font-medium hover:bg-mk-surfaceHover transition-colors">
                        Reset
                    </a>
                </div>
            </form>

            {{-- ===== TABLE ===== --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-mk-border">
                    <thead class="bg-mk-surface">
                        <tr>
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Tanggal</th>
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Jam</th>
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Murid</th>
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Label Sesi</th>
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Guru</th>
                            <th class="px-2 py-1.5 text-left text-xs uppercase font-medium">Ruang</th>
                            <th class="px-2 py-1.5 text-center text-xs uppercase font-medium">Status</th>
                            <th class="px-2 py-1.5 text-right text-xs uppercase font-medium">Honor</th>
                            @if($canEdit)
                            <th class="px-2 py-1.5 text-center text-xs uppercase font-medium">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mk-border">
                        @forelse($sessions as $s)
                            <tr class="hover:bg-mk-surface">
                                <td class="px-2 py-1.5 whitespace-nowrap text-sm">
                                    {{ \Carbon\Carbon::parse($s->session_date)->format('D, d M') }}
                                </td>
                                <td class="px-2 py-1.5 font-mono text-xs whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}
                                    –
                                    {{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}
                                </td>
                                <td class="px-2 py-1.5">
                                    <a href="{{ route('students.show', $s->student_id) }}"
                                       class="text-blue-600 hover:underline">
                                        {{ $s->student->full_name ?? '?' }}
                                    </a>
                                    <span class="text-xs text-mk-dim font-mono ml-1">
                                        {{ $s->student->student_code ?? '' }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5">
                                    @php $label = $s->getSessionLabel(); @endphp
                                    @if($label !== '—')
                                        <span class="px-1.5 py-0.5 rounded text-[11px] font-medium
                                            {{ $s->origin_session_id ? 'bg-blue-50 text-blue-600' : 'bg-yellow-50 text-yellow-700' }}">
                                            {{ $label }}
                                        </span>
                                    @else
                                        <span class="text-mk-dim text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-sm">
                                    @if($s->substituteTeacher)
                                        {{ $s->substituteTeacher->name }}
                                        <span class="text-xs text-orange-600">(pengganti)</span>
                                        <span class="text-xs text-mk-dim line-through ml-1">
                                            {{ $s->teacher->name ?? '' }}
                                        </span>
                                    @else
                                        {{ $s->teacher->name ?? '?' }}
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 font-mono text-xs">
                                    {{ $s->room?->code ?? '—' }}
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <span class="px-2 py-0.5 text-xs rounded {{ $statusColors[$s->status] ?? '' }}">
                                        {{ $s->status }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-right whitespace-nowrap text-xs">
                                    @if($s->honor_amount)
                                        Rp {{ number_format($s->honor_amount, 0, ',', '.') }}
                                        <div class="text-mk-dim">{{ $s->honor_code }}</div>
                                    @else
                                        <span class="text-mk-dim">—</span>
                                    @endif
                                </td>
                                @if($canEdit)
                                <td class="px-2 py-1.5 text-center whitespace-nowrap">
                                    @if($s->status === 'SCHEDULED')
                                    <button type="button"
                                            @click="editSession = {
                                                id: {{ $s->id }},
                                                action: '{{ route('sessions.update', $s->id) }}',
                                                sessionDate: '{{ \Carbon\Carbon::parse($s->session_date)->format('D, d M Y') }}',
                                                startTime: '{{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}',
                                                endTime: '{{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}',
                                                teacherId: {{ $s->teacher_id ?? 'null' }},
                                                roomId: {{ $s->room_id ?? 'null' }}
                                            }"
                                            class="text-xs text-indigo-600 hover:underline px-1">
                                        Edit
                                    </button>
                                    @endif
                                    @if(in_array($s->status, ['SCHEDULED', 'LIBUR']))
                                    <form method="POST"
                                          action="{{ route('sessions.destroy', $s->id) }}"
                                          class="inline"
                                          onsubmit="return confirm('Hapus sesi {{ \Carbon\Carbon::parse($s->session_date)->format('d M Y') }} ({{ $s->status }})?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:underline px-1">Hapus</button>
                                    </form>
                                    @endif
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canEdit ? 8 : 7 }}" class="px-2 py-6 text-center text-mk-dim">
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

            <div class="mt-4">
                {{ $sessions->links() }}
            </div>

            {{-- ===== MODAL EDIT SESI ===== --}}
            @if($canEdit)
            <div x-show="editSession !== null" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                 @click.self="editSession = null">
                <div class="bg-mk-card rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-semibold text-mk-text">
                            Edit Sesi — <span x-text="editSession?.sessionDate" class="font-mono"></span>
                        </h3>
                        <button @click="editSession = null"
                                class="text-mk-dim hover:text-mk-muted text-lg leading-none">&times;</button>
                    </div>

                    <form :action="editSession?.action" method="POST" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-mk-muted mb-1">Jam Mulai</label>
                                <input type="time" name="start_time"
                                       :value="editSession?.startTime"
                                       required
                                       class="block w-full border-mk-border rounded-lg text-sm px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-mk-muted mb-1">Jam Selesai</label>
                                <input type="time" name="end_time"
                                       :value="editSession?.endTime"
                                       required
                                       class="block w-full border-mk-border rounded-lg text-sm px-3 py-2">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-mk-muted mb-1">Guru</label>
                            <select name="teacher_id" required
                                    class="block w-full border-mk-border rounded-lg text-sm px-3 py-2">
                                <option value="">— Pilih Guru —</option>
                                @foreach($teachers as $t)
                                <option value="{{ $t->id }}"
                                        :selected="editSession?.teacherId == {{ $t->id }}">
                                    {{ $t->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-mk-muted mb-1">
                                Ruang <span class="text-mk-dim">(opsional)</span>
                            </label>
                            <select name="room_id"
                                    class="block w-full border-mk-border rounded-lg text-sm px-3 py-2">
                                <option value="">— Tidak Ditentukan —</option>
                                @foreach($rooms as $r)
                                <option value="{{ $r->id }}"
                                        :selected="editSession?.roomId == {{ $r->id }}">
                                    {{ $r->code }} — {{ $r->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="editSession = null"
                                    class="px-4 py-2 text-xs bg-mk-surface rounded-lg hover:bg-mk-surfaceHover">
                                Batal
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 text-xs font-bold rounded-lg btn-mk-primary">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        </div>

    </div>

    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
