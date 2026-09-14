<section id="servicios" class="bg-e360-surface py-20 sm:py-24 lg:py-28" aria-labelledby="servicios-titulo">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title eyebrow="Servicios" subtitle="Además de los cursos, acompañamos a personas y organizaciones con estos servicios.">
            <span id="servicios-titulo">Nuestros servicios</span>
        </x-landing.section-title>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($institucion['servicios'] as $indice => $servicio)
                <div x-data x-reveal.{{ $indice * 60 }} @class(['h-full', 'sm:col-span-2' => $loop->first])>
                    <x-landing.service-card :servicio="$servicio" :featured="$loop->first" />
                </div>
            @endforeach
        </div>
    </div>
</section>
