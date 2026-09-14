@props([
    'label',
    'value',
    'icon' => 'chart-bar',
    'color' => 'ink',
    'valueColor' => null,
    'href' => null,
    'sublabel' => null,
])

@php
    $paletaChip = [
        'accent' => 'bg-accent/10 text-accent',
        'ok' => 'bg-ok/10 text-ok',
        'warn' => 'bg-warn/10 text-warn',
        'danger' => 'bg-danger/10 text-danger',
        'info' => 'bg-info/10 text-info',
    ];

    $paletaValor = [
        'accent' => 'text-accent',
        'ok' => 'text-ok',
        'warn' => 'text-warn',
        'danger' => 'text-danger',
        'info' => 'text-info',
    ];

    $estiloChip = $paletaChip[$color] ?? 'bg-ink-faint/10 text-ink-dim';
    $estiloValor = $paletaValor[$valueColor ?? $color] ?? 'text-ink';

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'group rounded-2xl border border-border bg-surface p-4 shadow-sm transition '.($href ? 'hover:-translate-y-0.5 hover:border-accent/40 hover:shadow-md' : '')]) }}
>
    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl {{ $estiloChip }}">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
    </span>

    <p class="mt-3 font-mono text-xs uppercase tracking-wide text-ink-faint">{{ $label }}</p>
    <p class="mt-0.5 font-display text-2xl {{ $estiloValor }}">{{ $value }}</p>
    @if ($sublabel)
        <p class="mt-0.5 text-xs text-ink-faint">{{ $sublabel }}</p>
    @endif
</{{ $tag }}>
