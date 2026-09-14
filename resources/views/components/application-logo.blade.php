@props(['iconOnly' => false])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 font-display text-ink']) }}>
    <x-brand-logo variant="icon" size="sm" />
    @unless($iconOnly)
        <span class="sidebar-label sidebar-label-brand text-sm leading-tight">{{ config('institucion.nombre') }}</span>
    @endunless
</div>
