<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-mk-text">Edit Template WA</h2>
            <a href="{{ route('whatsapp-templates.index') }}" class="text-sm text-mk-muted hover:text-mk-text">← Kembali</a>
        </div>
    </x-slot>
    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6 max-w-2xl">
            <form action="{{ route('whatsapp-templates.update', $template) }}" method="POST">
                @csrf
                @method('PUT')
                @include('whatsapp-templates._form', ['template' => $template])
                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('whatsapp-templates.index') }}" class="px-4 py-2 bg-mk-surface rounded text-sm">Batal</a>
                    <button type="submit" class="px-4 py-2 rounded text-sm font-bold btn-mk-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
