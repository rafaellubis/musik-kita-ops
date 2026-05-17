<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Tambah Konfigurasi Honor</h2>
                <div class="text-xs text-mk-muted mt-0.5">Payroll Config</div>
            </div>
            <a href="{{ route('payroll-configs.index') }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6 max-w-2xl">
            <form action="{{ route('payroll-configs.store') }}" method="POST">
                @csrf
                @include('payroll-configs._form')
                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('payroll-configs.index') }}"
                       class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm">Batal</a>
                    <button type="submit"
                            class="px-4 py-2 rounded text-sm font-bold transition-colors btn-mk-primary"
                            >Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
