<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Edit Guru</h2>
                <div class="text-xs text-mk-muted mt-0.5">{{ $teacher->code }} — {{ $teacher->name }}</div>
            </div>
            <a href="{{ route('teachers.index') }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6 max-w-3xl">
            <form action="{{ route('teachers.update', $teacher->id) }}" method="POST">
                @csrf @method('PUT')
                @include('teachers._form', ['teacher' => $teacher, 'instruments' => $instruments])
                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('teachers.index') }}"
                       class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-bold transition-colors"
                            style="background:#D4A853;color:#1A1000">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
