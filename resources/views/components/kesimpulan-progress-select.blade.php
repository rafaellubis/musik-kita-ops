@props(['name' => 'kesimpulan_progress', 'value' => null, 'idPrefix' => 'kesimpulan-progress'])

<fieldset>
    <legend class="font-semibold text-sm text-on-surface mb-3">Kesimpulan Progress</legend>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach(\App\Models\ProgressReport::kesimpulanLabels() as $key => $label)
            @php($id = "{$idPrefix}-{$key}")
            <div class="relative">
                <input id="{{ $id }}" type="radio" name="{{ $name }}" value="{{ $key }}" class="sr-only peer"
                       aria-describedby="{{ $errors->has($name) ? $name.'-error' : null }}"
                       aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
                       @checked(old($name, $value) === $key)>
                <label for="{{ $id }}" class="block cursor-pointer text-center text-xs border border-outline-variant/50 rounded-lg px-2 py-3 text-on-surface hover:border-secondary/50 transition-colors
                            peer-checked:border-secondary peer-checked:bg-secondary-container peer-checked:text-on-secondary-container peer-checked:font-semibold shadow-sm">
                    {{ $label }}
                </label>
            </div>
        @endforeach
    </div>
    @error($name)
        <p id="{{ $name }}-error" class="text-error text-xs mt-1">{{ $message }}</p>
    @enderror
</fieldset>
