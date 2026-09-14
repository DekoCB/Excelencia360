<section class="border-t border-e360-border bg-white py-20 sm:py-24" aria-labelledby="propuesta-valor-titulo">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title eyebrow="Propuesta de valor" subtitle="Cuatro principios que sostienen cada uno de nuestros cursos y servicios.">
            <span id="propuesta-valor-titulo">Lo que nos define</span>
        </x-landing.section-title>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($institucion['propuesta_valor'] as $indice => $bloque)
                <div x-data x-reveal.{{ $indice * 80 }}>
                    <x-landing.feature-card :icon="$bloque['icono']" :title="$bloque['titulo']">
                        {{ $bloque['descripcion'] }}
                    </x-landing.feature-card>
                </div>
            @endforeach
        </div>
    </div>
</section>
