@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-mk-border focus:border-mk-accent focus:ring-mk-accent rounded-md shadow-sm']) }}>
