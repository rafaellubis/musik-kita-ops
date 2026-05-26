<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-mk-sidebar border border-transparent rounded-md font-semibold text-xs text-mk-accent uppercase tracking-widest hover:bg-mk-muted active:bg-mk-muted focus:outline-none focus:ring-2 focus:ring-mk-accent focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
