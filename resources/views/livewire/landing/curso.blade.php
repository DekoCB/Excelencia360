<?php

use App\Shared\Support\Institucion;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Página pública de un curso (/cursos/{slug}). Los datos salen de
 * config/institucion.php; la ficha solo muestra los campos definidos.
 */
new #[Layout('layouts.landing')] class extends Component
{
    /** @var array<string, mixed> */
    public array $curso = [];

    public function mount(string $slug): void
    {
        $this->curso = Institucion::curso($slug) ?? abort(404);
    }

    public function rendering(View $view): void
    {
        $view->title($this->curso['nombre'].' | '.Institucion::nombre())
            ->layoutData(['metaDescription' => $this->curso['descripcion']]);
    }

    public function with(): array
    {
        $ficha = array_filter([
            ['icono' => 'computer-desktop', 'etiqueta' => 'Modalidad', 'valor' => $this->curso['modalidad'] ?? null],
            ['icono' => 'clock', 'etiqueta' => 'Duración', 'valor' => $this->curso['duracion'] ?? null],
            ['icono' => 'calendar-days', 'etiqueta' => 'Horario', 'valor' => $this->curso['horario'] ?? null],
            ['icono' => 'check-badge', 'etiqueta' => 'Certificación', 'valor' => $this->curso['certificacion'] ?? null],
            ['icono' => 'user-circle', 'etiqueta' => 'Docente', 'valor' => $this->curso['docente'] ?? null],
            ['icono' => 'banknotes', 'etiqueta' => 'Inversión', 'valor' => $this->curso['precio'] ?? null],
        ], fn (array $dato) => filled($dato['valor']));

        $otrosCursos = array_values(array_filter(
            Institucion::cursos(),
            fn (array $otro) => $otro['slug'] !== $this->curso['slug'],
        ));

        return [
            'ficha' => $ficha,
            'otrosCursos' => $otrosCursos,
            'asunto' => 'Curso: '.$this->curso['nombre'],
        ];
    }
}; ?>

<div>
    <x-landing.navbar />

    <main id="contenido">
        <section class="bg-e360-surface py-14 sm:py-16 lg:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <nav aria-label="Ruta de navegación" class="text-sm text-e360-muted">
                    <ol class="flex flex-wrap items-center gap-1.5">
                        <li><a href="{{ route('landing') }}" class="rounded-sm transition-colors hover:text-e360-primary-deep">Inicio</a></li>
                        <li aria-hidden="true"><x-heroicon-o-chevron-right class="h-3.5 w-3.5" /></li>
                        <li><a href="{{ route('landing') }}#cursos" class="rounded-sm transition-colors hover:text-e360-primary-deep">Cursos</a></li>
                        <li aria-hidden="true"><x-heroicon-o-chevron-right class="h-3.5 w-3.5" /></li>
                        <li class="font-medium text-e360-heading" aria-current="page">{{ $curso['nombre'] }}</li>
                    </ol>
                </nav>

                <div class="mt-8 grid items-start gap-10 lg:grid-cols-12 lg:gap-16">
                    <div x-data x-reveal class="lg:col-span-7">
                        <span class="inline-flex items-center gap-2 rounded-full bg-e360-primary-tint px-3 py-1 text-xs font-bold text-e360-primary-deep">
                            <x-landing.marca-360 class="h-3.5 w-3.5" />
                            {{ $curso['categoria'] }}
                        </span>
                        <h1 class="mt-4 font-brand text-3xl font-extrabold tracking-tight text-e360-heading sm:text-4xl lg:text-5xl">
                            {{ $curso['nombre'] }}
                        </h1>
                        @if (! empty($curso['subtitulo']))
                            <p class="mt-2 text-lg font-semibold text-e360-secondary-dark">{{ $curso['subtitulo'] }}</p>
                        @endif
                        <p class="mt-6 max-w-2xl text-lg leading-relaxed text-e360-text">{{ $curso['descripcion'] }}</p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <x-landing.cta-button :href="route('landing', ['asunto' => $asunto]).'#contacto'" size="lg" icon="arrow-right">
                                Solicitar información
                            </x-landing.cta-button>
                            <x-landing.cta-button :href="route('landing').'#cursos'" variant="secondary" size="lg">
                                Ver todos los cursos
                            </x-landing.cta-button>
                        </div>
                    </div>

                    <aside x-data x-reveal.150 class="lg:col-span-5">
                        <div class="overflow-hidden rounded-2xl border border-e360-border bg-white shadow-sm shadow-e360-heading/5">
                            <div class="relative flex aspect-[16/9] items-center justify-center bg-e360-primary-tint">
                                @if (! empty($curso['imagen']))
                                    <img src="{{ asset($curso['imagen']) }}" alt="" class="h-full w-full object-cover">
                                @else
                                    <x-landing.marca-360 :dot="false" class="absolute -right-10 -top-12 h-52 w-52 text-e360-primary/25" />
                                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-e360-primary-deep shadow-md shadow-e360-primary-deep/10">
                                        <x-dynamic-component :component="'heroicon-o-'.($curso['icono'] ?? 'academic-cap')" class="h-8 w-8" aria-hidden="true" />
                                    </div>
                                @endif
                            </div>
                            <div class="p-6">
                                <h2 class="font-brand text-base font-bold text-e360-heading">Ficha del curso</h2>
                                <dl class="mt-3 divide-y divide-e360-border">
                                    @foreach ($ficha as $dato)
                                        <div class="flex items-center gap-3 py-3">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-e360-secondary-tint text-e360-secondary-dark">
                                                <x-dynamic-component :component="'heroicon-o-'.$dato['icono']" class="h-4 w-4" aria-hidden="true" />
                                            </span>
                                            <dt class="w-28 text-xs font-semibold uppercase tracking-wide text-e360-muted">{{ $dato['etiqueta'] }}</dt>
                                            <dd class="text-sm font-medium text-e360-heading">{{ $dato['valor'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                                @if (count($ficha) < 6)
                                    <p class="mt-3 text-xs text-e360-muted">Los demás detalles del curso se publicarán próximamente. Si quieres saber más, solicita información.</p>
                                @endif
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        @if ($otrosCursos !== [])
            <section class="bg-white py-16 sm:py-20" aria-labelledby="otros-cursos-titulo">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <x-landing.section-title eyebrow="Cursos" align="left" :compact="true">
                        <span id="otros-cursos-titulo">Otros cursos</span>
                    </x-landing.section-title>
                    <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($otrosCursos as $indice => $otro)
                            <div x-data x-reveal.{{ $indice * 80 }} class="h-full">
                                <x-landing.course-card :curso="$otro" />
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </main>

    <x-landing.footer />
</div>
