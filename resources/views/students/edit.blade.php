<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">
                Edit: {{ $student->student_code }} — {{ $student->full_name }}
            </h2>
            <a href="{{ route('students.show', $student->id) }}"
               class="text-sm text-gray-600 hover:underline">
                ← Batal & kembali
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                {{-- Info Status Lock --}}
                <div class="mb-6 p-3 bg-amber-50 border border-amber-200 rounded text-sm text-amber-800">
                    <strong>Status saat ini: {{ $student->status }}.</strong>
                    Status hanya bisa diubah lewat tombol aksi di halaman Detail,
                    bukan dari form ini.
                </div>

                <form action="{{ route('students.update', $student->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @include('students._form', [
                        'student' => $student,
                        'mode' => 'edit'
                    ])

                    <div class="flex justify-end gap-2 mt-6 pt-6 border-t">
                        <a href="{{ route('students.show', $student->id) }}"
                           class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                            Batal
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>