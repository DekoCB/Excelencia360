<section id="inicio" class="relative overflow-hidden bg-white">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 pb-16 pt-12 sm:px-6 lg:grid-cols-12 lg:gap-8 lg:px-8 lg:pb-24 lg:pt-20">
        <div x-data x-reveal class="lg:col-span-6">
            <p class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-e360-primary-deep">
                <x-landing.marca-360 class="h-3.5 w-3.5 shrink-0" />
                Formación y capacitación · Modalidad virtual
            </p>

            <h1 class="mt-5 font-brand text-4xl font-extrabold leading-[1.1] tracking-tight text-e360-heading sm:text-5xl lg:text-[3.4rem] xl:text-6xl">
                Educación que <span class="text-e360-primary-deep">impulsa</span> tu futuro
            </h1>

            <p class="mt-6 max-w-xl text-lg leading-relaxed text-e360-text">
                Formación y capacitación especializada para desarrollar tus competencias y prepararte para los nuevos desafíos profesionales.
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-landing.cta-button href="#cursos" size="lg" icon="arrow-right">Explorar cursos</x-landing.cta-button>
                <x-landing.cta-button href="#conocenos" variant="secondary" size="lg">Conócenos</x-landing.cta-button>
            </div>

            <p class="mt-8 flex items-center gap-2 text-sm text-e360-muted">
                <x-heroicon-o-map-pin class="h-4 w-4 shrink-0 text-e360-primary-dark" aria-hidden="true" />
                {{ $institucion['ciudad'] }}, {{ $institucion['pais'] }} · Institución de formación y capacitación
            </p>
        </div>

        <div x-data x-reveal.150 class="lg:col-span-6">
            <x-landing.hero-illustration />
        </div>
    </div>
</section>
