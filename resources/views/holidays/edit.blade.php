<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Edit: {{ $holiday->name }}</h2></x-slot>
    <div class="py-12"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <form action="{{ route('holidays.update', $holiday->id) }}" method="POST">
                @csrf
                @method('PUT')
                @include('holidays._form', ['holiday' => $holiday])
                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('holidays.index') }}" class="px-4 py-2 bg-gray-200 rounded">Batal</a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Update</button>
                </div>
            </form>
        </div>
    </div></div>
</x-app-layout>