<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Laporan Murid — {{ $monthName }}</h2>
            <div class="flex items-center gap-3 no-print">
                <form method="GET" action="{{ route('reports.students') }}" class="flex items-center gap-2">
                    <select name="year" class="border-gray-300 rounded text-sm py-1">
                        @foreach(range(now()->year, now()->year - 2) as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                    <select name="month" class="border-gray-300 rounded text-sm py-1">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ Carbon\Carbon::create(null, $m, 1)->format('M') }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 rounded text-sm">Tampil</button>
                </form>
                <a href="{{ route('students.index') }}" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 rounded text-sm">
                    Data Murid →
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ===== STATISTIK BULAN INI ===== --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-700">{{ $muridBaru }}</div>
                    <div class="text-xs text-gray-500 mt-1">Murid Baru Aktif</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-red-600">{{ $muridMundur }}</div>
                    <div class="text-xs text-gray-500 mt-1">Mundur / Selesai</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-700">{{ $byStatus['Aktif'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500 mt-1">Murid Aktif</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-gray-700">{{ array_sum($byStatus) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Total Terdaftar</div>
                </div>
            </div>

            {{-- ===== DISTRIBUSI PER STATUS ===== --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h3 class="font-semibold text-sm">Distribusi per Status</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody>
                            @php
                                $statusColors = [
                                    'Aktif'               => 'bg-green-100 text-green-700',
                                    'Trial'               => 'bg-yellow-100 text-yellow-700',
                                    'Calon'               => 'bg-gray-100 text-gray-600',
                                    'Cuti'                => 'bg-orange-100 text-orange-700',
                                    'Selesai'             => 'bg-blue-100 text-blue-700',
                                    'Mengundurkan Diri'   => 'bg-red-100 text-red-700',
                                ];
                                $total = max(1, array_sum($byStatus));
                            @endphp
                            @foreach($statusColors as $status => $colorClass)
                                @php $count = $byStatus[$status] ?? 0; @endphp
                                @if($count > 0)
                                <tr class="border-b">
                                    <td class="px-4 py-2 w-8/12">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded text-xs {{ $colorClass }}">{{ $status }}</span>
                                            {{-- Progress bar --}}
                                            <div class="flex-1 bg-gray-100 rounded h-1.5">
                                                <div class="{{ str_replace('text-', 'bg-', explode(' ', $colorClass)[1]) }} h-1.5 rounded"
                                                     style="width: {{ round($count / $total * 100) }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ $count }}</td>
                                    <td class="px-4 py-2 text-right text-gray-400 text-xs">
                                        {{ round($count / $total * 100) }}%
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                            <tr class="bg-gray-50">
                                <td class="px-4 py-2 font-medium">Total</td>
                                <td class="px-4 py-2 text-right font-bold">{{ array_sum($byStatus) }}</td>
                                <td class="px-4 py-2 text-right text-gray-400 text-xs">100%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ===== DISTRIBUSI PER INSTRUMEN ===== --}}
            @if($byInstrument->count() > 0)
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h3 class="font-semibold text-sm">Distribusi Murid Aktif per Instrumen</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-2 text-left">Instrumen</th>
                                <th class="px-4 py-2 text-right">Murid Aktif</th>
                                <th class="px-4 py-2 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalAktif = $byInstrument->sum('total'); @endphp
                            @foreach($byInstrument as $row)
                            <tr class="border-b">
                                <td class="px-4 py-2 font-medium">{{ $row->instr_name }}</td>
                                <td class="px-4 py-2 text-right font-semibold">{{ $row->total }}</td>
                                <td class="px-4 py-2 text-right text-gray-400 text-xs">
                                    {{ $totalAktif > 0 ? round($row->total / $totalAktif * 100) : 0 }}%
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-4 py-2 font-medium">Total</td>
                                <td class="px-4 py-2 text-right font-bold">{{ $totalAktif }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
