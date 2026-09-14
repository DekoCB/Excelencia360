<section id="cursos" class="bg-white py-20 sm:py-24 lg:py-28" aria-labelledby="cursos-titulo">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title eyebrow="Cursos" subtitle="Programas de formación en modalidad virtual, orientados al desarrollo de competencias.">
            <span id="cursos-titulo">Nuestros cursos</span>
        </x-landing.section-title>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($institucion['cursos'] as $indice => $curso)
                <div x-data x-reveal.{{ $indice * 80 }} class="h-full">
                    <x-landing.course-card :curso="$curso" />
                </div>
            @endforeach
        </div>
    </div>
</section>
