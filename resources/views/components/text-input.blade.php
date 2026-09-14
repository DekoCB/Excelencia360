@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-md border-border bg-surface text-ink shadow-sm focus:border-accent focus:ring-accent disabled:opacity-60']) }}>
