<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Tambah Murid Baru</h2>
                <div class="text-xs text-mk-muted mt-0.5">Data Murid</div>
            </div>
            <a href="{{ route('students.index') }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm rounded-2xl p-6 md:p-8 max-w-4xl">
            <form action="{{ route('students.store') }}" method="POST">
                @csrf

                @include('students._form', [
                    'student' => null,
                    'mode' => 'create'
                ])

                <div class="flex justify-end gap-2 mt-6 pt-6 border-t border-mk-borderLight">
                    <a href="{{ route('students.index') }}"
                       class="px-4 py-2 bg-mk-surface hover:bg-mk-surfaceHover rounded-lg text-sm border border-secondary/10 transition-colors">
                        Batal
                    </a>
                    <button type="submit"
                            class="px-6 py-2 rounded text-sm font-bold transition-colors btn-mk-primary"
                            >
                        Simpan Murid Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
