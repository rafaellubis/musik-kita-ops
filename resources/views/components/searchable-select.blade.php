@props([
    'name',
    'label' => null,
    'placeholder' => 'Pilih...',
    'selected' => null,
    'options' => [],
    'autoSubmit' => false,
    'required' => false,
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
    $componentId = 'searchable-select-'.md5($name.'-'.json_encode($normalizedOptions));
    $triggerId = $componentId.'-trigger';
    $comboboxId = $componentId.'-combobox';
    $listboxId = $componentId.'-listbox';
@endphp

<div
    x-data="{
        open: false,
        search: '',
        activeIndex: -1,
        autoSubmit: @js($autoSubmit),
        name: @js($name),
        placeholder: @js($placeholder),
        selectedValue: @js($selectedValue),
        options: @js($normalizedOptions),
        get selectedLabel() {
            if (!this.selectedValue) return this.placeholder;
            return this.options.find((option) => option.value === this.selectedValue)?.label ?? this.placeholder;
        },
        get filteredOptions() {
            const term = this.search.trim().toLowerCase();
            const options = term ? this.options.filter((option) => option.label.toLowerCase().includes(term)) : this.options;
            return [{ value: '', label: this.placeholder }, ...options];
        },
        get activeDescendant() {
            return this.open && this.activeIndex >= 0 ? @js($componentId) + '-option-' + this.activeIndex : null;
        },
        openList() {
            this.open = true;
            this.search = '';
            this.activeIndex = Math.max(0, this.filteredOptions.findIndex((option) => option.value === this.selectedValue));
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },
        toggle() {
            this.open ? this.close() : this.openList();
        },
        close() {
            this.open = false;
            this.search = '';
            this.activeIndex = -1;
            this.$nextTick(() => this.$refs.combobox?.focus());
        },
        moveActive(step) {
            if (!this.open) this.openList();
            const count = this.filteredOptions.length;
            if (!count) return;
            this.activeIndex = (this.activeIndex + step + count) % count;
            this.$nextTick(() => document.getElementById(this.activeDescendant)?.scrollIntoView({ block: 'nearest' }));
        },
        selectActive() {
            if (!this.open) return this.openList();
            const option = this.filteredOptions[this.activeIndex];
            if (option) this.select(option.value);
        },
        submitForm() {
            if (this.autoSubmit) this.$nextTick(() => this.$el.closest('form')?.requestSubmit());
        },
        select(value) {
            this.selectedValue = value;
            this.$dispatch('searchable-select-changed', { name: this.name, value });
            this.close();
            this.submitForm();
        },
    }"
    class="relative"
    @click.outside="if (open) close()"
>
    @if($label)
        <span id="{{ $componentId }}-label" class="block text-xs text-mk-dim mb-1">{{ $label }}</span>
    @endif

    <input type="hidden" :name="name" :value="selectedValue" @if($required) required @endif>

    <button
        id="{{ $triggerId }}"
        x-ref="combobox"
        type="button"
        aria-haspopup="listbox"
        :aria-expanded="open"
        @if($label) aria-labelledby="{{ $componentId }}-label {{ $triggerId }}-value" @endif
        @click="toggle()"
        @keydown.arrow-down.prevent="openList()"
        @keydown.arrow-up.prevent="openList()"
        @keydown.escape.prevent="close()"
        class="{{ $inputClass }} px-3 py-2 text-left flex items-center justify-between gap-2 transition-colors"
    >
        <span id="{{ $triggerId }}-value" class="truncate" x-text="selectedLabel"></span>
        <svg class="w-4 h-4 shrink-0 text-mk-dim transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition class="mk-searchable-select-panel absolute z-50 mt-1 w-full rounded-lg shadow-lg overflow-hidden bg-white border border-gray-200">
        <div class="mk-searchable-select-panel-header p-2">
            <input
                id="{{ $comboboxId }}"
                type="text"
                x-ref="searchInput"
                x-model="search"
                role="combobox"
                aria-haspopup="listbox"
                aria-controls="{{ $listboxId }}"
                :aria-expanded="open"
                :aria-activedescendant="activeDescendant"
                @if($label) aria-labelledby="{{ $componentId }}-label" @else aria-label="Cari pilihan" @endif
                @input="activeIndex = 0"
                @click.stop
                @keydown.arrow-down.prevent="moveActive(1)"
                @keydown.arrow-up.prevent="moveActive(-1)"
                @keydown.enter.prevent="selectActive()"
                @keydown.escape.prevent="close()"
                placeholder="Cari..."
                class="block w-full rounded text-sm px-2 py-1.5 bg-white border border-gray-200"
            >
        </div>

        <ul id="{{ $listboxId }}" role="listbox" class="max-h-52 overflow-y-auto py-1 text-sm">
            <template x-for="(option, index) in filteredOptions" :key="option.value">
                <li
                    :id="@js($componentId) + '-option-' + index"
                    role="option"
                    :aria-selected="selectedValue === option.value"
                    @mouseenter="activeIndex = index"
                    @click="select(option.value)"
                    class="px-3 py-2 text-left hover:bg-mk-surfaceHover transition-colors truncate cursor-pointer"
                    :class="{
                        'bg-mk-surface font-medium': selectedValue === option.value,
                        'bg-mk-surfaceHover': activeIndex === index,
                    }"
                    x-text="option.label"
                ></li>
            </template>

            <li x-show="filteredOptions.length === 1 && search" class="px-3 py-2 text-mk-dim text-xs">
                Tidak Ada Hasil.
            </li>
        </ul>
    </div>
</div>
