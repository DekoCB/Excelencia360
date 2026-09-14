<section id="mision-vision" class="bg-white py-20 sm:py-24 lg:py-28" aria-labelledby="mision-vision-titulo">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title eyebrow="Nuestro rumbo" subtitle="Hacia dónde vamos y cómo lo hacemos.">
            <span id="mision-vision-titulo">Misión y visión</span>
        </x-landing.section-title>

        <div class="mx-auto grid max-w-5xl grid-cols-1 gap-6 lg:grid-cols-2">
            <div x-data x-reveal>
                <x-landing.mission-vision kind="mision" title="Misión">{{ $institucion['mision'] }}</x-landing.mission-vision>
            </div>
            <div x-data x-reveal.120>
                <x-landing.mission-vision kind="vision" title="Visión">{{ $institucion['vision'] }}</x-landing.mission-vision>
            </div>
        </div>
    </div>
</section>
