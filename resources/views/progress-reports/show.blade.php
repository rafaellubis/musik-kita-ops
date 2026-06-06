<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Laporan: {{ $progressReport->student->full_name }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $progressReport->teacher->name }} · {{ $progressReport->enrollment->package->code }} · {{ $progressReport->namaBulan() }}
                </div>
            </div>
            @if($progressReport->status === 'SUBMITTED')
                <a href="{{ route('progress-reports.pdf', $progressReport) }}" class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">
                    ↓ Download PDF
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 max-w-3xl">
        {{-- Checklist per seksi --}}
        @foreach($progressReport->template->sections as $section)
            @php $sectionRecord = $progressReport->sections->firstWhere('report_template_section_id', $section->id); @endphp
            <div class="bg-white shadow-sm rounded-lg mb-4 overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50 font-semibold text-sm text-gray-700">{{ $section->title }}</div>
                @if($sectionRecord?->summary)
                    <div class="px-5 py-2 text-sm text-gray-600 italic border-b">{{ $sectionRecord->summary }}</div>
                @endif
                <div class="px-5 py-3 space-y-1.5">
                    @foreach($section->items as $item)
                        @php
                            $itemRecord = $progressReport->items->firstWhere('report_template_item_id', $item->id);
                            $checked = $itemRecord?->is_checked ?? false;
                        @endphp
                        <div class="flex items-center gap-2 text-sm">
                            <span class="{{ $checked ? 'text-green-600' : 'text-gray-300' }}">{{ $checked ? '✓' : '○' }}</span>
                            <span class="{{ $checked ? 'text-gray-700' : 'text-gray-400' }}">{{ $item->label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if($progressReport->repertoire)
            <div class="bg-white shadow-sm rounded-lg mb-4 p-5">
                <div class="font-semibold text-sm text-gray-700 mb-2">Repertoar</div>
                <ul class="list-disc list-inside space-y-1 text-sm text-gray-600">
                    @foreach($progressReport->repertoire as $lagu)<li>{{ $lagu }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if($progressReport->highlight)
            <div class="bg-white shadow-sm rounded-lg mb-4 p-5">
                <div class="font-semibold text-sm text-gray-700 mb-2">Highlight Pencapaian</div>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $progressReport->highlight }}</p>
            </div>
        @endif

        @if($progressReport->sessionNotes->isNotEmpty())
            <div class="bg-white shadow-sm rounded-lg mb-4 p-5">
                <div class="font-semibold text-sm text-gray-700 mb-3">Catatan Per Sesi</div>
                @foreach($progressReport->sessionNotes->sortBy([['session_date', 'asc'], ['sort_order', 'asc']]) as $note)
                    @php
                        $hasStructured = filled($note->material_learned) || filled($note->homework_notes);
                    @endphp
                    <div class="mb-3 border-b border-gray-50 pb-3 last:border-0">
                        <div class="text-xs font-semibold text-gray-500 mb-2">
                            {{ \Carbon\Carbon::parse($note->session_date)->locale('id')->isoFormat('D MMMM Y') }}
                            @if($note->session_sequence)
                                · Sesi ke-{{ $note->session_sequence }}
                            @endif
                        </div>
                        @if($hasStructured)
                            <div class="space-y-2 text-sm text-gray-600">
                                @if(filled($note->material_learned))
                                    <div>
                                        <span class="font-medium text-gray-700">Materi:</span>
                                        <p class="whitespace-pre-line">{{ $note->material_learned }}</p>
                                    </div>
                                @endif
                                @if(filled($note->homework_notes))
                                    <div>
                                        <span class="font-medium text-gray-700">Tugas & Latihan:</span>
                                        <p class="whitespace-pre-line">{{ $note->homework_notes }}</p>
                                    </div>
                                @endif
                                @if(filled($note->notes))
                                    <div>
                                        <span class="font-medium text-gray-700">Catatan:</span>
                                        <p class="whitespace-pre-line">{{ $note->notes }}</p>
                                    </div>
                                @endif
                            </div>
                        @elseif(filled($note->notes))
                            <p class="text-sm text-gray-600 whitespace-pre-line">{{ $note->notes }}</p>
                        @else
                            <p class="text-sm text-gray-400 italic">Belum diisi</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if($progressReport->summary_notes || $progressReport->target_notes)
            <div class="bg-white shadow-sm rounded-lg mb-4 p-5 space-y-4">
                @if($progressReport->summary_notes)
                    <div>
                        <div class="font-semibold text-sm text-gray-700 mb-1">Catatan Akhir</div>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $progressReport->summary_notes }}</p>
                    </div>
                @endif
                @if($progressReport->target_notes)
                    <div>
                        <div class="font-semibold text-sm text-gray-700 mb-1">Target Bulan Depan</div>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $progressReport->target_notes }}</p>
                    </div>
                @endif
            </div>
        @endif

        <a href="{{ route('progress-reports.index') }}" class="text-sm text-gray-500 hover:underline">← Kembali</a>
    </div>
</x-app-layout>
