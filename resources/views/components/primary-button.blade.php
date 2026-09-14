<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-md bg-accent px-4 py-2 font-display text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
