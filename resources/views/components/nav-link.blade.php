@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center border-b-2 border-accent px-1 pt-1 text-sm font-medium leading-5 text-ink transition duration-150 ease-in-out focus:outline-none'
            : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-ink-faint transition duration-150 ease-in-out hover:border-border hover:text-ink focus:border-border focus:text-ink focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
