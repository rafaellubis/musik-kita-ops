<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Hari Libur {{ $year }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $holidays->count() }} hari libur terdaftar</div>
            </div>
            @role('Owner|Admin')
            <a href="{{ route('holidays.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary"
               >
                + Tambah Libur
            </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">

        {{-- Filter tahun --}}
        <div class="mb-4">
            <form method="GET" class="inline-flex items-center gap-2">
                <label class="text-xs text-mk-muted font-medium">Tahun:</label>
                <select name="year" onchange="this.form.submit()"
                        class="border-mk-border rounded-md text-sm">
                    @foreach($availableYears as $y)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                    @if(!$availableYears->contains($year))
                        <option value="{{ $year }}" selected>{{ $year }}</option>
                    @endif
                </select>
            </form>
        </div>

        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-mk-border">
                <thead class="bg-mk-surface">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Tanggal</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Nama</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Tipe</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Tgl Pengganti</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Catatan</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Status</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mk-border">
                    @php
                        $typeBadge = [
                            'Nasional'      => 'bg-red-100 text-red-800',
                            'Cuti Bersama'  => 'bg-amber-100 text-amber-800',
                            'Internal'      => 'bg-blue-100 text-blue-800',
                        ];
                        $dayName = ['Sun'=>'Min','Mon'=>'Sen','Tue'=>'Sel','Wed'=>'Rab','Thu'=>'Kam','Fri'=>'Jum','Sat'=>'Sab'];
                    @endphp
                    @forelse($holidays as $h)
                        <tr class="hover:bg-mk-surface">
                            <td class="px-4 py-2 text-sm">
                                <span class="font-mono">{{ $h->date->format('d M Y') }}</span>
                                <span class="ml-2 text-xs text-mk-dim">
                                    ({{ $dayName[$h->date->format('D')] ?? $h->date->format('D') }})
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm">{{ $h->name }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs {{ $typeBadge[$h->type] ?? 'bg-mk-surface' }}">
                                    {{ $h->type }}
                                </span>
                                @if(!$h->is_honor_paid)
                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] bg-orange-100 text-orange-700">Honor Nol</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm">
                                @if($h->replacement_date)
                                    <span class="font-mono text-green-700">
                                        {{ \Carbon\Carbon::parse($h->replacement_date)->format('d M Y') }}
                                    </span>
                                    <div class="text-[10px] text-mk-dim">
                                        ({{ ['Sun'=>'Min','Mon'=>'Sen','Tue'=>'Sel','Wed'=>'Rab','Thu'=>'Kam','Fri'=>'Jum','Sat'=>'Sab'][\Carbon\Carbon::parse($h->replacement_date)->format('D')] ?? '' }})
                                    </div>
                                @else
                                    <span class="text-xs text-mk-dim italic">Tidak Ada Pergantian Kelas</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-mk-muted">{{ $h->notes ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($h->is_active)
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-mk-surface text-mk-muted">Off</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('holidays.edit', $h->id) }}"
                                   class="text-blue-600 hover:underline">Edit</a>
                                @role('Owner')
                                <form action="{{ route('holidays.destroy', $h->id) }}"
                                      method="POST" class="inline ml-2"
                                      onsubmit="return confirm('Hapus hari libur {{ $h->date->format('d M Y') }} ({{ $h->name }})?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                </form>
                                @endrole
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-mk-dim">
                                Belum ada hari libur untuk tahun {{ $year }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-app-layout>
