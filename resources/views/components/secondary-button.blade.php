<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-mk-card border border-mk-border rounded-md font-semibold text-xs text-mk-muted uppercase tracking-widest shadow-sm hover:bg-mk-surface focus:outline-none focus:ring-2 focus:ring-mk-accent focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
