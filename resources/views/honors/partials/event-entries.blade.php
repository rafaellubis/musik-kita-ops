@php
    $eventEntries = $honor->eventHonorEntries()->where('status', 'POSTED')
        ->with(['event', 'participant.student'])->orderBy('event_id')->orderBy('id')->get();
@endphp
@if($eventEntries->isNotEmpty())
    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm text-mk-text">
            <caption class="mb-2 text-left font-semibold">Rincian Honor Event</caption>
            <thead>
                <tr class="border-b border-mk-borderLight bg-mk-surface">
                    <th scope="col" class="p-2 text-left">Event</th>
                    <th scope="col" class="p-2 text-left">Murid / Paket</th>
                    <th scope="col" class="p-2 text-right">Honor (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($eventEntries as $entry)
                    <tr class="border-b border-mk-borderLight">
                        <td class="p-2">{{ $entry->event->name }}<br>{{ $entry->event->event_date->format('d/m/Y') }}</td>
                        <td class="p-2">{{ $entry->participant->student->full_name }}<br>{{ $entry->snapshot_package_code }}</td>
                        <td class="p-2 text-right font-mono">{{ number_format($entry->amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
