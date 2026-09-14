@props(['icon', 'title'])

<article {{ $attributes->class(['group h-full rounded-2xl border border-e360-border bg-white p-6 transition duration-300 hover:-translate-y-1 hover:border-e360-primary/40 hover:shadow-xl hover:shadow-e360-heading/5']) }}>
    <x-landing.icon-circle :icon="$icon" tone="primary" :interactive="true" />
    <h3 class="mt-5 font-brand text-lg font-bold text-e360-heading">{{ $title }}</h3>
    <p class="mt-2 text-sm leading-relaxed text-e360-text">{{ $slot }}</p>
</article>
