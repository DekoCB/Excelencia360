@php
    // Ideas que ya están en la descripción oficial, resaltadas como lista.
    $pilares = [
        ['icono' => 'squares-2x2', 'texto' => 'Formación en diferentes áreas'],
        ['icono' => 'shield-check', 'texto' => 'Sólida base ética'],
        ['icono' => 'briefcase', 'texto' => 'Capaces de actuar en el mundo laboral'],
        ['icono' => 'flag', 'texto' => 'Compromiso con el desarrollo del país'],
    ];

    $ficha = [
        ['etiqueta' => 'Razón social', 'valor' => $institucion['razon_social']],
        ['etiqueta' => 'RUC', 'valor' => $institucion['ruc']],
        ['etiqueta' => 'Inicio de actividades', 'valor' => $institucion['inicio_actividades']],
        ['etiqueta' => 'Actividad', 'valor' => $institucion['actividad']],
        ['etiqueta' => 'Sede', 'valor' => $institucion['direccion'].', '.$institucion['ciudad'].', '.$institucion['pais']],
        ['etiqueta' => 'Gerente General', 'valor' => $institucion['gerente_general']],
    ];
@endphp

<section id="conocenos" class="bg-e360-surface py-20 sm:py-24 lg:py-28" aria-labelledby="conocenos-titulo">
    <div class="mx-auto grid max-w-7xl items-start gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:gap-16 lg:px-8">
        <div x-data x-reveal class="lg:col-span-7">
            <x-landing.section-title eyebrow="Conócenos" align="left" :compact="true">
                <span id="conocenos-titulo">{{ mb_strtoupper($institucion['nombre']) }}</span>
            </x-landing.section-title>

            <p class="text-lg leading-relaxed text-e360-text sm:text-xl">
                {{ $institucion['descripcion'] }}
            </p>

            <ul class="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($pilares as $pilar)
                    <li class="flex items-center gap-3 rounded-xl border border-e360-border bg-white px-4 py-3 text-sm font-medium text-e360-heading">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-e360-primary-tint text-e360-primary-deep">
                            <x-dynamic-component :component="'heroicon-o-'.$pilar['icono']" class="h-[18px] w-[18px]" aria-hidden="true" />
                        </span>
                        {{ $pilar['texto'] }}
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">
                <x-landing.cta-button href="#mision-vision" variant="ghost" icon="arrow-down" size="sm" class="-ml-4">Ver misión y visión</x-landing.cta-button>
            </div>
        </div>

        <aside x-data x-reveal.150 class="lg:col-span-5">
            <div class="rounded-2xl border border-e360-border bg-white p-6 shadow-sm shadow-e360-heading/5 sm:p-8">
                <p class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-e360-secondary-dark">
                    <x-heroicon-o-building-office-2 class="h-4 w-4" aria-hidden="true" />
                    Ficha institucional
                </p>
                <dl class="mt-4 divide-y divide-e360-border">
                    @foreach ($ficha as $dato)
                        <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-5 sm:gap-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-e360-muted sm:col-span-2 sm:pt-0.5">{{ $dato['etiqueta'] }}</dt>
                            <dd class="text-sm font-medium text-e360-heading sm:col-span-3">{{ $dato['valor'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </aside>
    </div>
</section>
