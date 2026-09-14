@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-4 border-accent bg-accent-soft py-2 ps-3 pe-4 text-start text-base font-medium text-accent transition duration-150 ease-in-out focus:outline-none'
            : 'block w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-ink-dim transition duration-150 ease-in-out hover:border-border hover:bg-surface-2 hover:text-ink focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
