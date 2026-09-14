@props(['entrada'])

{{--
    Tarjeta de publicación del blog. Espera un arreglo con titulo, extracto,
    categoria, fecha (Y-m-d), autor, imagen (ruta en public/) y url; todo
    salvo el título es opcional. Se usa en cuanto config('institucion.blog')
    tenga publicaciones reales.
--}}
@php
    $fecha = ! empty($entrada['fecha']) ? \Illuminate\Support\Carbon::parse($entrada['fecha']) : null;
@endphp

<article {{ $attributes->class(['group flex h-full flex-col overflow-hidden rounded-2xl border border-e360-border bg-white transition duration-300 hover:-translate-y-1 hover:border-e360-primary/40 hover:shadow-xl hover:shadow-e360-heading/5']) }}>
    <div class="relative aspect-[16/9] overflow-hidden bg-e360-secondary-tint">
        @if (! empty($entrada['imagen']))
            <img src="{{ asset($entrada['imagen']) }}" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
        @else
            <div class="flex h-full items-center justify-center text-e360-secondary/40">
                <x-heroicon-o-newspaper class="h-12 w-12" aria-hidden="true" />
            </div>
        @endif
        @if (! empty($entrada['categoria']))
            <span class="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1 text-xs font-bold text-e360-secondary-dark shadow-sm">{{ $entrada['categoria'] }}</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-6">
        @if ($fecha)
            <time datetime="{{ $fecha->toDateString() }}" class="text-xs font-medium text-e360-muted">{{ $fecha->translatedFormat('d \d\e F \d\e Y') }}</time>
        @endif
        <h3 class="mt-2 font-brand text-lg font-bold leading-snug text-e360-heading">
            @if (! empty($entrada['url']))
                <a href="{{ $entrada['url'] }}" class="rounded-sm transition-colors hover:text-e360-primary-deep focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-e360-secondary">{{ $entrada['titulo'] }}</a>
            @else
                {{ $entrada['titulo'] }}
            @endif
        </h3>
        @if (! empty($entrada['extracto']))
            <p class="mt-3 text-sm leading-relaxed text-e360-text">{{ $entrada['extracto'] }}</p>
        @endif
        <div class="mt-auto flex items-center justify-between gap-4 pt-6">
            @if (! empty($entrada['autor']))
                <p class="flex items-center gap-1.5 text-xs text-e360-muted">
                    <x-heroicon-o-user-circle class="h-4 w-4" aria-hidden="true" />
                    {{ $entrada['autor'] }}
                </p>
            @endif
            @if (! empty($entrada['url']))
                <a href="{{ $entrada['url'] }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-e360-primary-deep transition-colors hover:text-e360-primary-deeper">
                    Leer más
                    <x-heroicon-o-arrow-right class="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                </a>
            @endif
        </div>
    </div>
</article>
