<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Kalender Jadwal</h2>
        <div class="text-xs text-mk-muted mt-0.5">
            Jadwal sesi minggu ini — read-only
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 space-y-4">

        {{-- ===== WEEK NAVIGATOR ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">

                {{-- Navigasi prev / label / next --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('kalender.index', $prevWeek) }}"
                       class="px-3 py-1.5 rounded text-sm border border-gray-200 hover:bg-gray-50 transition-colors">
                        ← Minggu Lalu
                    </a>
                    <span class="px-4 py-1.5 text-sm font-semibold text-gray-800 whitespace-nowrap">
                        {{ $weekStart->translatedFormat('d M') }}
                        –
                        {{ $weekEnd->translatedFormat('d M Y') }}
                    </span>
                    <a href="{{ route('kalender.index', $nextWeek) }}"
                       class="px-3 py-1.5 rounded text-sm border border-gray-200 hover:bg-gray-50 transition-colors">
                        Minggu Depan →
                    </a>
                </div>

                {{-- Tombol Minggu Ini --}}
                <a href="{{ route('kalender.index', $currentWeek) }}"
                   class="px-3 py-1.5 rounded text-sm border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">
                    Minggu Ini
                </a>

            </div>
        </div>

        {{-- ===== FILTER BAR ===== --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4"
             x-data>
            <form method="GET" action="{{ route('kalender.index') }}" id="filter-form">
                {{-- Pertahankan week aktif --}}
                <input type="hidden" name="week" value="{{ $weekStart->format('Y-m-d') }}">

                <div class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Guru</label>
                        <select name="teacher_id"
                                class="border-gray-300 rounded text-sm"
                                @change="$el.form.submit()">
                            <option value="">Semua Guru</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}"
                                    {{ request('teacher_id') == $t->id ? 'selected' : '' }}>
                                    {{ $t->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Ruangan</label>
                        <select name="room_id"
                                class="border-gray-300 rounded text-sm"
                                @change="$el.form.submit()">
                            <option value="">Semua Ruangan</option>
                            @foreach($rooms as $r)
                                <option value="{{ $r->id }}"
                                    {{ request('room_id') == $r->id ? 'selected' : '' }}>
                                    {{ $r->code }} — {{ $r->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(request('teacher_id') || request('room_id'))
                        <a href="{{ route('kalender.index', ['week' => $weekStart->format('Y-m-d')]) }}"
                           class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded transition-colors">
                            Reset Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Grid diisi di Task 4 --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <p class="text-sm text-gray-500">Grid sedang dibangun (Task 4).</p>
        </div>

    </div>
    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
