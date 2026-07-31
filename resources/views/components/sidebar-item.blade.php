@props([
    'route',
    'icon',
    'label',
    'active' => false,
    'badge'  => null,
    'disabled' => false,
    'title' => null,
])

@php
    $baseClass = 'flex items-center gap-2.5 px-4 py-2 rounded-full transition-all duration-150 select-none border border-transparent';
    $stateClass = match (true) {
        $disabled => 'text-white/30 cursor-not-allowed opacity-60',
        $active => 'bg-secondary text-white font-bold shadow-sm',
        default => 'text-white/60 hover:bg-white/5 hover:text-white/90',
    };
@endphp

@if($disabled)
    <span
        role="presentation"
        title="{{ $title ?? 'Menu tidak tersedia' }}"
        @class([$baseClass, $stateClass])>
        <span class="text-base leading-none shrink-0">{{ $icon }}</span>
        <span class="flex-1 truncate text-[13px]">{{ $label }}</span>
        @if($badge)
        <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
            {{ $badge }}
        </span>
        @endif
    </span>
@else
    <a href="{{ route($route) }}"
       @class([$baseClass, $stateClass])>
        <span class="text-base leading-none shrink-0">{{ $icon }}</span>
        <span class="flex-1 truncate text-[13px]">{{ $label }}</span>
        @if($badge)
        <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
            {{ $badge }}
        </span>
        @endif
    </a>
@endif

