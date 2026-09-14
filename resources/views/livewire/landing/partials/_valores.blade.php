<section id="valores" class="bg-e360-surface py-20 sm:py-24 lg:py-28" aria-labelledby="valores-titulo">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title eyebrow="Valores" subtitle="Los principios que guían nuestro trabajo con cada estudiante.">
            <span id="valores-titulo">Nuestros valores</span>
        </x-landing.section-title>

        <div class="mx-auto grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
            @foreach ($institucion['valores'] as $indice => $valor)
                <div x-data x-reveal.{{ $indice * 60 }}>
                    <x-landing.value-card :icon="$valor['icono']" :name="$valor['nombre']">
                        {{ $valor['descripcion'] }}
                    </x-landing.value-card>
                </div>
            @endforeach
        </div>
    </div>
</section>
