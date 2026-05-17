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

        @if(session('error'))
        <div class="mb-5 p-3 rounded-lg text-sm max-w-3xl"
             style="background:rgba(239,68,68,0.1);color:#EF4444;border:1px solid rgba(239,68,68,0.2)">
            {{ session('error') }}
        </div>
        @endif
        @if(session('warning'))
        <div class="mb-5 p-3 rounded-lg text-sm max-w-3xl"
             style="background:rgba(251,191,36,0.1);color:#F59E0B;border:1px solid rgba(251,191,36,0.2)">
            ⚠️ {{ session('warning') }}
        </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6 max-w-3xl">
            <form action="{{ route('teachers.update', $teacher->id) }}" method="POST">
                @csrf @method('PUT')
                @include('teachers._form', ['teacher' => $teacher, 'instruments' => $instruments])
                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('teachers.index') }}"
                       class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-bold transition-colors btn-mk-primary"
                            >Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
