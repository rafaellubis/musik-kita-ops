<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">{{ $expense->expense_number }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $expense->expense_date->format('d M Y') }} · {{ $expense->category->name ?? '?' }}
                </div>
            </div>
            <a href="{{ route('expenses.index', ['year' => $expense->expense_date->year, 'month' => $expense->expense_date->month]) }}"
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
                    <div class="font-mono text-xs text-mk-dim">{{ $expense->expense_number }}</div>
                    <div class="text-xl font-bold mt-1">{{ $expense->description }}</div>
                    <div class="text-sm text-mk-dim mt-1">
                        {{ $expense->expense_date->format('d M Y') }} ·
                        <span class="font-medium">{{ $expense->category->name ?? '?' }}</span> ·
                        <span class="{{ $expense->payment_method === 'CASH' ? 'text-green-700' : 'text-blue-700' }}">
                            {{ $expense->payment_method }}
                        </span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-red-700">
                        Rp {{ number_format($expense->amount, 0, ',', '.') }}
                    </div>
                    <div class="flex gap-2 mt-2 justify-end">
                        @hasanyrole('Owner|Admin')
                            <a href="{{ route('expenses.edit', $expense) }}"
                               class="px-3 py-1 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700">
                                Edit
                            </a>
                        @endhasanyrole
                        @if($isOwner)
                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                                  onsubmit="return confirm('Hapus pengeluaran ini?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="px-3 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700">
                                    Hapus
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            @if($expense->notes)
                <div class="text-sm text-mk-muted mb-4 p-3 bg-mk-surface rounded">
                    {{ $expense->notes }}
                </div>
            @endif

            <div class="text-xs text-mk-dim">
                Dicatat oleh {{ $expense->createdBy->name ?? '—' }} ·
                {{ $expense->created_at->format('d M Y H:i') }}
            </div>
        </div>

        {{-- Foto bukti --}}
        @if($expense->receipt_image)
            <div class="bg-mk-card shadow-sm sm:rounded-lg p-4">
                <h3 class="text-sm font-medium text-mk-muted mb-3">Foto Bukti</h3>
                <img src="{{ asset('storage/' . $expense->receipt_image) }}"
                     alt="Bukti pengeluaran"
                     class="max-w-full rounded border border-mk-border"
                     style="max-height: 400px; object-fit: contain;">
            </div>
        @endif

    </div>
</x-app-layout>
