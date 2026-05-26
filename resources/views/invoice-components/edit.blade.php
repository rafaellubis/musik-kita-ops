<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Edit Komponen Tagihan</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $invoiceComponent->code }}</div>
            </div>
            <a href="{{ route('invoice-components.index') }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6 max-w-2xl">
            <form action="{{ route('invoice-components.update', $invoiceComponent->id) }}" method="POST">
                @csrf
                @method('PUT')
                @include('invoice-components._form', ['invoiceComponent' => $invoiceComponent])
                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('invoice-components.index') }}"
                       class="px-4 py-2 bg-mk-surface hover:bg-mk-surfaceHover rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-bold transition-colors btn-mk-primary"
                            >Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
