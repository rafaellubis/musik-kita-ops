@props([
    'route',
    'icon',
    'label',
    'active' => false,
    'badge'  => null,
])

<a href="{{ route($route) }}"
   @class([
       'flex items-center gap-2.5 px-3 py-1.5 rounded-lg transition-all duration-150 select-none',
       // state aktif
       'bg-mk-accentDim text-mk-accent font-semibold border-l-[3px] border-mk-accent pl-[9px]' => $active,
       // state normal
       'text-white/60 hover:bg-white/5 hover:text-white/90 border-l-[3px] border-transparent'   => !$active,
   ])>
    <span class="text-base leading-none shrink-0">{{ $icon }}</span>
    <span class="flex-1 truncate text-[13px]">{{ $label }}</span>
    @if($badge)
    <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
        {{ $badge }}
    </span>
    @endif
</a>

