<section id="blog" class="bg-white py-20 sm:py-24 lg:py-28" aria-labelledby="blog-titulo">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title eyebrow="Blog" subtitle="Artículos y novedades sobre formación, capacitación y aprendizaje virtual.">
            <span id="blog-titulo">Blog</span>
        </x-landing.section-title>

        @if ($institucion['blog'] !== [])
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($institucion['blog'] as $indice => $entrada)
                    <div x-data x-reveal.{{ $indice * 80 }} class="h-full">
                        <x-landing.blog-card :entrada="$entrada" />
                    </div>
                @endforeach
            </div>
        @else
            {{-- Estado vacío: todavía no hay publicaciones reales, y no se muestran ficticias. --}}
            <div x-data x-reveal class="mx-auto flex max-w-2xl flex-col items-center rounded-2xl border border-dashed border-e360-border bg-e360-surface px-6 py-12 text-center sm:py-16">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-e360-secondary-dark shadow-sm">
                    <x-heroicon-o-newspaper class="h-7 w-7" aria-hidden="true" />
                </span>
                <h3 class="mt-5 font-brand text-xl font-bold text-e360-heading">Pronto publicaremos nuestras primeras entradas</h3>
                <p class="mt-2 max-w-md text-sm leading-relaxed text-e360-text">
                    Estamos preparando artículos y novedades. Mientras tanto, puedes conocer nuestros cursos o escribirnos si tienes una consulta.
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <x-landing.cta-button href="#cursos" size="sm">Explorar cursos</x-landing.cta-button>
                    <x-landing.cta-button href="#contacto" variant="secondary" size="sm">Contáctanos</x-landing.cta-button>
                </div>
            </div>
        @endif
    </div>
</section>
