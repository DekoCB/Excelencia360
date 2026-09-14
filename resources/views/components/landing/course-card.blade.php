@props(['curso'])

{{--
    Tarjeta de curso. La cabecera usa la imagen del curso si existe
    (clave "imagen" en config/institucion.php) y, si no, una ilustración de
    marca con el ícono del curso. La ficha (duración, certificación,
    precio...) solo lista los campos con valor: los que aún no se definen
    no se inventan. "Más información" lleva al formulario de contacto con el
    asunto ya elegido (ver el listener data-asunto en landing/index).
--}}
@php
    $asunto = 'Curso: '.$curso['nombre'];
    $enlaceContacto = route('landing', ['asunto' => $asunto]).'#contacto';
    $ficha = array_filter([
        ['icono' => 'computer-desktop', 'etiqueta' => 'Modalidad', 'valor' => $curso['modalidad'] ?? null],
        ['icono' => 'clock', 'etiqueta' => 'Duración', 'valor' => $curso['duracion'] ?? null],
        ['icono' => 'check-badge', 'etiqueta' => 'Certificación', 'valor' => $curso['certificacion'] ?? null],
        ['icono' => 'banknotes', 'etiqueta' => 'Inversión', 'valor' => $curso['precio'] ?? null],
    ], fn (array $dato) => filled($dato['valor']));
@endphp

<article {{ $attributes->class(['group flex h-full flex-col overflow-hidden rounded-2xl border border-e360-border bg-white transition duration-300 hover:-translate-y-1 hover:border-e360-primary/40 hover:shadow-xl hover:shadow-e360-heading/5']) }}>
    <div class="relative aspect-[16/9] overflow-hidden bg-e360-primary-tint">
        @if (! empty($curso['imagen']))
            <img src="{{ asset($curso['imagen']) }}" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
        @else
            <x-landing.marca-360 :dot="false" class="absolute -right-10 -top-12 h-52 w-52 text-e360-primary/25 transition duration-500 group-hover:rotate-12" />
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-e360-primary-deep shadow-md shadow-e360-primary-deep/10">
                    <x-dynamic-component :component="'heroicon-o-'.($curso['icono'] ?? 'academic-cap')" class="h-8 w-8" aria-hidden="true" />
                </div>
            </div>
        @endif
        <span class="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1 text-xs font-bold text-e360-primary-deep shadow-sm">
            {{ $curso['categoria'] }}
        </span>
    </div>

    <div class="flex flex-1 flex-col p-6">
        <h3 class="font-brand text-lg font-bold leading-snug text-e360-heading">
            <a href="{{ route('landing.curso', $curso['slug']) }}" class="rounded-sm transition-colors hover:text-e360-primary-deep focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-e360-secondary">{{ $curso['nombre'] }}</a>
        </h3>
        @if (! empty($curso['subtitulo']))
            <p class="mt-1 text-sm font-semibold text-e360-secondary-dark">{{ $curso['subtitulo'] }}</p>
        @endif
        <p class="mt-3 text-sm leading-relaxed text-e360-text">{{ $curso['descripcion'] }}</p>

        @if ($ficha !== [])
            <dl class="mt-4 flex flex-wrap gap-x-4 gap-y-2">
                @foreach ($ficha as $dato)
                    <div class="flex items-center gap-1.5 text-xs text-e360-muted">
                        <x-dynamic-component :component="'heroicon-o-'.$dato['icono']" class="h-4 w-4 shrink-0 text-e360-primary-dark" aria-hidden="true" />
                        <dt class="sr-only">{{ $dato['etiqueta'] }}</dt>
                        <dd>{{ $dato['valor'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-3 pt-6">
            <x-landing.cta-button :href="route('landing.curso', $curso['slug'])" size="sm">Ver curso</x-landing.cta-button>
            <a
                href="{{ $enlaceContacto }}"
                data-asunto="{{ $asunto }}"
                class="inline-flex items-center gap-1.5 rounded-md text-sm font-semibold text-e360-secondary-dark transition-colors hover:text-e360-secondary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-e360-secondary"
            >
                Más información
                <x-heroicon-o-arrow-right class="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </a>
        </div>
    </div>
</article>
