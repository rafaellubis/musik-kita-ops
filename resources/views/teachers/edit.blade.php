<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Edit Guru: {{ $teacher->code }}</h2></x-slot>
    <div class="py-12"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <form action="{{ route('teachers.update', $teacher->id) }}" method="POST">
                @csrf @method('PUT')
                @include('teachers._form', ['teacher' => $teacher, 'instruments' => $instruments])
                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('teachers.index') }}" class="px-4 py-2 bg-gray-200 rounded">Batal</a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Update</button>
                </div>
            </form>
        </div>
    </div></div>
</x-app-layout>
