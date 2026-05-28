@php
    $map = [
        'HADIR'           => ['bg-green-100 text-green-700',   'Hadir'],
        'HADIR_TERLAMBAT' => ['bg-yellow-100 text-yellow-700', 'Terlambat'],
        'SCHEDULED'       => ['bg-gray-100 text-gray-500',     'Terjadwal'],
        'LIBUR'           => ['bg-purple-100 text-purple-700', 'Libur'],
        'HANGUS'          => ['bg-red-100 text-red-600',       'Hangus'],
        'IZIN_RESCHEDULE' => ['bg-orange-100 text-orange-600', 'Izin'],
        'IZIN_VIDEO'      => ['bg-orange-100 text-orange-600', 'Izin Video'],
        'DIGANTI'         => ['bg-blue-100 text-blue-600',     'Diganti'],
        'CANCELLED'       => ['bg-gray-200 text-gray-500',     'Batal'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-gray-100 text-gray-500', $status];
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $cls }}">
    {{ $label }}
</span>
