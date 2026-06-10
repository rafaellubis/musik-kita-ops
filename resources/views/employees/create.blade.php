<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Tambah Karyawan</h2>
                <div class="text-xs text-mk-muted mt-0.5">Master Data — Gaji Staff</div>
            </div>
            <a href="{{ route('employees.index') }}" class="text-sm text-mk-muted hover:text-mk-text">← Kembali</a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg p-6 max-w-3xl">
            <form action="{{ route('employees.store') }}" method="POST">
                @csrf
                @include('employees._form', ['users' => $users])
                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('employees.index') }}"
                       class="px-4 py-2 bg-mk-surface hover:bg-mk-surfaceHover rounded text-sm">Batal</a>
                    <button type="submit" class="px-4 py-2 rounded text-sm font-bold btn-mk-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
