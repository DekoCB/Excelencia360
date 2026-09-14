@props(['eyebrow' => null, 'subtitle' => null, 'align' => 'center', 'level' => 'h2', 'compact' => false])

<div @class(['max-w-2xl', 'mx-auto text-center' => $align === 'center', 'mb-6' => $compact, 'mb-12 sm:mb-14' => ! $compact])>
    @if ($eyebrow)
        <p class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-e360-primary-deep">
            <x-landing.marca-360 class="h-3.5 w-3.5 shrink-0" />
            {{ $eyebrow }}
        </p>
    @endif

    <{{ $level }} @class(['font-brand text-3xl font-extrabold tracking-tight text-e360-heading sm:text-4xl', 'mt-3' => $eyebrow])>
        {{ $slot }}
    </{{ $level }}>

    @if ($subtitle)
        <p class="mt-4 text-base leading-relaxed text-e360-muted sm:text-lg">{{ $subtitle }}</p>
    @endif
</div>
