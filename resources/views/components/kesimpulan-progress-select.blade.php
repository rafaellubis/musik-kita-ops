@props(['name' => 'kesimpulan_progress', 'value' => null])

<div>
    <div class="font-semibold text-sm text-on-surface mb-3">Kesimpulan Progress</div>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach(\App\Models\ProgressReport::kesimpulanLabels() as $key => $label)
            <label class="relative cursor-pointer">
                <input type="radio" name="{{ $name }}" value="{{ $key }}" class="sr-only peer"
                       @checked(old($name, $value) === $key)>
                <div class="text-center text-xs border border-outline-variant/50 rounded-lg px-2 py-3 text-on-surface hover:border-secondary/50 transition-colors
                            peer-checked:border-secondary peer-checked:bg-secondary-container peer-checked:text-on-secondary-container peer-checked:font-semibold shadow-sm">
                    {{ $label }}
                </div>
            </label>
        @endforeach
    </div>
    @error($name)
        <p class="text-error text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
