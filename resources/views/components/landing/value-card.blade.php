@props(['icon', 'name'])

{{-- Tarjeta de valor institucional: al pasar el cursor, el disco del ícono se rellena de turquesa. --}}
<article {{ $attributes->class(['group flex h-full items-start gap-4 rounded-2xl border border-e360-border bg-white p-5 transition duration-300 hover:-translate-y-1 hover:border-e360-primary/40 hover:shadow-lg hover:shadow-e360-heading/5 sm:p-6']) }}>
    <x-landing.icon-circle :icon="$icon" tone="primary" :interactive="true" />
    <div>
        <h3 class="font-brand text-base font-bold text-e360-heading sm:text-lg">{{ $name }}</h3>
        <p class="mt-1.5 text-sm leading-relaxed text-e360-text">{{ $slot }}</p>
    </div>
</article>
