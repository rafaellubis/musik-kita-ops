<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">{{ $topup->topup_number }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    Isi Saldo · {{ $topup->topup_date->format('d M Y') }}
                </div>
            </div>
            <a href="{{ route('petty-cash.index', ['year' => $topup->topup_date->year, 'month' => $topup->topup_date->month]) }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    @php $isOwner = auth()->user()?->hasRole('Owner'); @endphp

    <div class="py-6 px-4 lg:px-8 space-y-4">
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <div class="font-mono text-xs text-mk-dim">{{ $topup->topup_number }}</div>
                    <div class="text-xl font-bold mt-1">{{ $topup->description }}</div>
                    <div class="text-sm text-mk-dim mt-1">{{ $topup->topup_date->format('d M Y') }}</div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-green-700">
                        + Rp {{ number_format($topup->amount, 0, ',', '.') }}
                    </div>
                    @if($isOwner)
                        <div class="flex gap-2 mt-2 justify-end">
                            <a href="{{ route('petty-cash.topups.edit', $topup) }}"
                               class="px-3 py-1 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700">
                                Edit
                            </a>
                            <form method="POST" action="{{ route('petty-cash.topups.destroy', $topup) }}"
                                  onsubmit="return confirm('Hapus isi saldo ini?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="px-3 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            @if($topup->notes)
                <div class="text-sm text-mk-muted mb-4 p-3 bg-mk-surface rounded">
                    {{ $topup->notes }}
                </div>
            @endif

            <div class="text-xs text-mk-dim">
                Dicatat oleh {{ $topup->createdBy->name ?? '—' }} ·
                {{ $topup->created_at->format('d M Y H:i') }}
            </div>
        </div>

        @if($topup->receipt_image)
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
                <h3 class="text-sm font-medium text-mk-muted mb-3">Foto Bukti</h3>
                <img src="{{ asset('storage/' . $topup->receipt_image) }}"
                     alt="Bukti isi saldo"
                     class="max-w-full rounded border border-mk-border"
                     style="max-height: 400px; object-fit: contain;">
            </div>
        @endif
    </div>
</x-app-layout>
