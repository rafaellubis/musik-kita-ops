@props(['name' => 'kesimpulan_progress', 'value' => null])

<div>
    <div class="font-semibold text-sm text-mk-text mb-2">Kesimpulan Progress</div>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach(\App\Models\ProgressReport::kesimpulanLabels() as $key => $label)
            <label class="cursor-pointer">
                <input type="radio" name="{{ $name }}" value="{{ $key }}" class="sr-only peer"
                       @checked(old($name, $value) === $key)>
                <div class="text-center text-xs border border-mk-border rounded-lg px-2 py-3
                            peer-checked:border-mk-accent peer-checked:bg-mk-accent/10 peer-checked:font-semibold">
                    {{ $label }}
                </div>
            </label>
        @endforeach
    </div>
    @error($name)
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
