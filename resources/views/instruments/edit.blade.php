<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit Instrumen: {{ $instrument->name }}
        </h2>
    </x-slot>
 
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
 
                <form action="{{ route('instruments.update', $instrument) }}" method="POST">
                    @csrf
                    @method('PUT')
 
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Code</label>
                        <input type="text" name="code"
                               value="{{ old('code', $instrument->code) }}"
                               class="mt-1 block w-full border rounded px-3 py-2 font-mono">
                        @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
 
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Nama Tampil</label>
                        <input type="text" name="name"
                               value="{{ old('name', $instrument->name) }}"
                               class="mt-1 block w-full border rounded px-3 py-2">
                        @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
 
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
                        <textarea name="description" rows="2"
                                  class="mt-1 block w-full border rounded px-3 py-2">{{ old('description', $instrument->description) }}</textarea>
                    </div>
 
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Urutan Tampil</label>
                        <input type="number" name="sort_order"
                               value="{{ old('sort_order', $instrument->sort_order) }}"
                               class="mt-1 block w-32 border rounded px-3 py-2">
                        @error('sort_order') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
 
                    <div class="mb-4">
                        <label class="inline-flex items-center">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $instrument->is_active) ? 'checked' : '' }} class="mr-2">
                            <span class="text-sm">Aktif</span>
                        </label>
                    </div>
 
                    <div class="flex space-x-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                            Simpan Perubahan
                        </button>
                        <a href="{{ route('instruments.index') }}"
                           class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded">Batal</a>
                    </div>
                </form>
 
            </div>
        </div>
    </div>
</x-app-layout>
