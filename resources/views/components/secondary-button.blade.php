<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center rounded-md border border-border bg-surface px-4 py-2 font-display text-xs font-semibold uppercase tracking-widest text-ink-dim shadow-sm transition duration-150 ease-in-out hover:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 disabled:opacity-25']) }}>
    {{ $slot }}
</button>
