<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Edit Event — {{ $event->name }}</h2>
            <a href="{{ route('events.show', $event) }}" class="text-sm text-gray-600 hover:underline">← Kembali</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('events.update', $event) }}">
                    @csrf @method('PATCH')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Nama Event <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required maxlength="100"
                               value="{{ old('name', $event->name) }}"
                               class="mt-1 block w-full border-gray-300 rounded @error('name') border-red-500 @enderror">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Tipe Event <span class="text-red-500">*</span></label>
                        <select name="type" required
                                class="mt-1 block w-full border-gray-300 rounded @error('type') border-red-500 @enderror">
                            <option value="MINI_CONCERT" {{ old('type', $event->type) === 'MINI_CONCERT' ? 'selected' : '' }}>
                                Mini Concert (tanpa ujian grade)
                            </option>
                            <option value="MINI_CONCERT_UJIAN" {{ old('type', $event->type) === 'MINI_CONCERT_UJIAN' ? 'selected' : '' }}>
                                Mini Concert + Ujian Grade
                            </option>
                            <option value="UJIAN" {{ old('type', $event->type) === 'UJIAN' ? 'selected' : '' }}>
                                Ujian Grade (tanpa concert)
                            </option>
                        </select>
                        @error('type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Tanggal Event <span class="text-red-500">*</span></label>
                        <input type="date" name="event_date" required
                               value="{{ old('event_date', $event->event_date->format('Y-m-d')) }}"
                               class="mt-1 block w-full border-gray-300 rounded @error('event_date') border-red-500 @enderror">
                        @error('event_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700">Catatan (opsional)</label>
                        <textarea name="notes" rows="3" maxlength="1000"
                                  class="mt-1 block w-full border-gray-300 rounded text-sm">{{ old('notes', $event->notes) }}</textarea>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                            Simpan Perubahan
                        </button>
                        <a href="{{ route('events.show', $event) }}"
                           class="px-5 py-2 text-gray-600 hover:text-gray-800 text-sm">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
