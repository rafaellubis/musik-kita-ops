@php
    $map = [
        'HADIR'           => ['bg-secondary-container text-on-secondary-container', 'Hadir'],
        'HADIR_TERLAMBAT' => ['bg-yellow-100 text-yellow-700', 'Terlambat'],
        'SCHEDULED'       => ['bg-surface-container-high text-on-surface-variant', 'Terjadwal'],
        'LIBUR'           => ['bg-purple-100 text-purple-700', 'Libur'],
        'HANGUS'          => ['bg-error-container text-on-error-container', 'Hangus'],
        'IZIN_RESCHEDULE' => ['bg-orange-100 text-orange-600', 'Izin'],
        'IZIN_VIDEO'      => ['bg-orange-100 text-orange-600', 'Izin Video'],
        'DIGANTI'         => ['bg-blue-100 text-blue-600',     'Diganti'],
        'CANCELLED'       => ['bg-surface-container-high text-on-surface-variant', 'Batal'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-surface-container-high text-on-surface-variant', $status];
@endphp
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold {{ $cls }}">
    {{ $label }}
</span>
