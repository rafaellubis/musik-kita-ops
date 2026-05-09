<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Edit Data Murid</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $student->student_code }} — {{ $student->full_name }}
                </div>
            </div>
            <a href="{{ route('students.show', $student->id) }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6 max-w-4xl">

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
                       class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm">
                        Batal
                    </a>
                    <button type="submit"
                            class="px-6 py-2 rounded text-sm font-bold transition-colors"
                            style="background:#D4A853;color:#1A1000">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
