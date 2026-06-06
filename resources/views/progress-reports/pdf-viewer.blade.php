@if(($layout ?? 'admin') === 'guru')
<x-guru-layout title="Preview PDF">
    <div class="px-4 pt-4 pb-2">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3">
            <div>
                <div class="text-sm text-mk-muted">
                    {{ $progressReport->student->full_name }} · {{ $progressReport->namaBulan() }}
                    @if($progressReport->report_number)
                        · <span class="font-mono">{{ $progressReport->report_number }}</span>
                    @endif
                    @if($progressReport->status === 'DRAFT')
                        · <span class="text-amber-600 font-semibold">Draft</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ $backUrl }}" class="px-4 py-2 rounded-lg text-sm font-medium border border-mk-border text-mk-text hover:bg-mk-card">
                    ← Kembali
                </a>
                <a href="{{ $downloadUrl }}" class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">
                    ↓ Download PDF
                </a>
            </div>
        </div>
    </div>

    <div class="px-4 pb-8">
        <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-mk-border">
            <iframe
                src="{{ $fileUrl }}"
                title="Laporan Progress {{ $progressReport->student->full_name }}"
                class="w-full border-0"
                style="height: calc(100vh - 14rem); min-height: 480px;"
            ></iframe>
        </div>
    </div>
</x-guru-layout>
@else
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Preview PDF</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $progressReport->student->full_name }} · {{ $progressReport->namaBulan() }}
                    @if($progressReport->report_number)
                        · <span class="font-mono">{{ $progressReport->report_number }}</span>
                    @endif
                    @if($progressReport->status === 'DRAFT')
                        · <span class="text-amber-600 font-semibold">Draft</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ $backUrl }}" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">
                    ← Kembali
                </a>
                <a href="{{ $downloadUrl }}" class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">
                    ↓ Download PDF
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4 px-4 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-200">
            <iframe
                src="{{ $fileUrl }}"
                title="Laporan Progress {{ $progressReport->student->full_name }}"
                class="w-full border-0"
                style="height: calc(100vh - 12rem); min-height: 480px;"
            ></iframe>
        </div>
    </div>
</x-app-layout>
@endif
