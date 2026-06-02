<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Laporan Progres Murid</h2>
        <div class="text-xs text-mk-muted mt-0.5">Laporan bulanan yang disubmit guru</div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        {{-- Filter --}}
        @php
            $teacherFilterOptions = $teachers->map(fn ($t) => [
                'value' => $t->id,
                'label' => $t->name,
            ])->values()->all();
        @endphp
        <form method="GET" class="mb-5 flex flex-wrap gap-3 items-end">
            <x-searchable-select
                name="teacher_id"
                placeholder="Semua Guru"
                :selected="request('teacher_id')"
                :options="$teacherFilterOptions"
                inputClass="mk-searchable-select-trigger rounded-lg text-sm min-w-[180px]"
            />
            <select name="status" class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2">
                <option value="">Semua Status</option>
                <option value="SUBMITTED" {{ request('status') === 'SUBMITTED' ? 'selected' : '' }}>Submitted</option>
                <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>Draft</option>
            </select>
            <select name="month" class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2">
                <option value="">Semua Bulan</option>
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create()->month($m)->locale('id')->isoFormat('MMMM') }}
                    </option>
                @endforeach
            </select>
            <input type="number" name="year" value="{{ request('year', now()->year) }}" min="2024" max="2030"
                   class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2 w-24">
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">Filter</button>
            <a href="{{ route('progress-reports.index') }}" class="text-xs text-mk-muted hover:text-mk-text">× Reset</a>
        </form>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if($laporan->isEmpty())
                <div class="p-8 text-center text-gray-400">Belum ada laporan.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="border-b text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3">Murid</th>
                            <th class="px-4 py-3">Guru</th>
                            <th class="px-4 py-3">Kelas</th>
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($laporan as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium text-gray-800">{{ $r->student->full_name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->teacher->name }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs font-mono">{{ $r->enrollment->package->code }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $r->namaBulan() }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-medium {{ $r->status === 'SUBMITTED' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $r->status === 'SUBMITTED' ? 'Submitted' : 'Draft' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('progress-reports.show', $r) }}" class="text-indigo-600 hover:underline text-xs mr-3">Detail</a>
                                @if($r->status === 'SUBMITTED')
                                    <a href="{{ route('progress-reports.pdf', $r) }}" class="text-green-600 hover:underline text-xs">↓ PDF</a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t">{{ $laporan->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
