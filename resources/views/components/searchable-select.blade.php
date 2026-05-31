@props([
    'name',
    'label' => null,
    'placeholder' => 'Pilih...',
    'selected' => null,
    'options' => [],
    'autoSubmit' => false,
    'inputClass' => 'mk-searchable-select-trigger block w-full rounded text-sm',
])

@php
    $selectedValue = (string) old($name, $selected ?? '');
    $normalizedOptions = collect($options)
        ->map(fn ($option) => [
            'value' => (string) ($option['value'] ?? ''),
            'label' => (string) ($option['label'] ?? ''),
        ])
        ->values()
        ->all();
@endphp

<div
    x-data="{
        open: false,
        search: '',
        autoSubmit: @js($autoSubmit),
        name: @js($name),
        placeholder: @js($placeholder),
        selectedValue: @js($selectedValue),
        options: @js($normalizedOptions),
        get selectedLabel() {
            if (!this.selectedValue) {
                return this.placeholder;
            }

            const match = this.options.find((option) => option.value === this.selectedValue);
            return match ? match.label : this.placeholder;
        },
        get filteredOptions() {
            const term = this.search.trim().toLowerCase();
            if (!term) {
                return this.options;
            }

            return this.options.filter((option) => option.label.toLowerCase().includes(term));
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.search = '';
                this.$nextTick(() => this.$refs.searchInput?.focus());
            }
        },
        close() {
            this.open = false;
            this.search = '';
        },
        submitForm() {
            if (!this.autoSubmit) {
                return;
            }

            this.$nextTick(() => this.$el.closest('form')?.requestSubmit());
        },
        select(value) {
            this.selectedValue = value;
            this.close();
            this.submitForm();
        },
    }"
    class="relative"
    @click.outside="close()"
    @keydown.escape.window="close()"
>
    @if($label)
        <label class="block text-xs text-mk-dim mb-1">{{ $label }}</label>
    @endif

    <input type="hidden" :name="name" :value="selectedValue">

    <button
        type="button"
        @click="toggle()"
        class="{{ $inputClass }} px-3 py-2 text-left flex items-center justify-between gap-2 transition-colors"
        :aria-expanded="open"
    >
        <span class="truncate" x-text="selectedLabel"></span>
        <svg class="w-4 h-4 shrink-0 text-mk-dim transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="mk-searchable-select-panel absolute z-50 mt-1 w-full rounded-lg shadow-lg overflow-hidden"
    >
        <div class="mk-searchable-select-panel-header p-2">
            <input
                type="text"
                x-ref="searchInput"
                x-model="search"
                @click.stop
                placeholder="Cari..."
                class="block w-full rounded text-sm px-2 py-1.5"
            >
        </div>

        <ul class="max-h-52 overflow-y-auto py-1 text-sm">
            <li>
                <button
                    type="button"
                    @click="select('')"
                    class="w-full px-3 py-2 text-left hover:bg-mk-surfaceHover transition-colors"
                    :class="selectedValue === '' ? 'bg-mk-surface font-medium' : ''"
                    x-text="placeholder"
                ></button>
            </li>

            <template x-for="option in filteredOptions" :key="option.value">
                <li>
                    <button
                        type="button"
                        @click="select(option.value)"
                        class="w-full px-3 py-2 text-left hover:bg-mk-surfaceHover transition-colors truncate"
                        :class="selectedValue === option.value ? 'bg-mk-surface font-medium' : ''"
                        x-text="option.label"
                    ></button>
                </li>
            </template>

            <li x-show="filteredOptions.length === 0" class="px-3 py-2 text-mk-dim text-xs">
                Tidak Ada Hasil.
            </li>
        </ul>
    </div>
</div>
