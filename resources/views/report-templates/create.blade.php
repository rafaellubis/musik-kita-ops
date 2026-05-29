<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Tambah Template Laporan</h2>
    </x-slot>
    <div class="py-6 px-4 lg:px-8 max-w-xl">
        <div class="bg-white shadow-sm rounded-lg p-6">
            <form method="POST" action="{{ route('report-templates.store') }}">
                @csrf
                @include('report-templates._form', ['template' => null])
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="px-5 py-2 rounded-lg text-sm font-bold btn-mk-primary">Simpan</button>
                    <a href="{{ route('report-templates.index') }}" class="px-5 py-2 rounded-lg text-sm border border-gray-200 text-gray-600 hover:bg-gray-50">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
