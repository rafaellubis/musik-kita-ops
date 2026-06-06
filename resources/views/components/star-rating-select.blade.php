@props(['name', 'label', 'value' => null])

<div>
    <label class="block text-sm font-medium text-mk-text mb-1">{{ $label }}</label>
    <select name="{{ $name }}"
            class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
        <option value="">— Pilih rating —</option>
        @for ($i = 1; $i <= 5; $i++)
            <option value="{{ $i }}" @selected((int) old($name, $value) === $i)>
                {{ str_repeat('★', $i) }}{{ str_repeat('☆', 5 - $i) }}
            </option>
        @endfor
    </select>
    @error($name)
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
