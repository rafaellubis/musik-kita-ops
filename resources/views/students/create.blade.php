<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Tambah Murid Baru</h2>
            <a href="{{ route('students.index') }}" class="text-sm text-gray-600 hover:underline">
                ← Batal & kembali
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form action="{{ route('students.store') }}" method="POST">
                    @csrf

                    @include('students._form', [
                        'student' => null,
                        'mode' => 'create'
                    ])

                    <div class="flex justify-end gap-2 mt-6 pt-6 border-t">
                        <a href="{{ route('students.index') }}"
                           class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                            Batal
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Simpan Murid Baru
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>