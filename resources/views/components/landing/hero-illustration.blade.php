{{--
    Ilustración del hero: el arco de 360° (turquesa → azul) que se dibuja al
    cargar y termina en el punto naranja, con tres piezas de interfaz del
    aula virtual encima: la tarjeta de un curso real y dos chips con las
    ideas centrales de la propuesta (accesible desde cualquier lugar,
    formación por competencias). Es composición HTML/SVG, sin imágenes:
    cuando existan fotos institucionales se reemplaza la tarjeta central.
--}}
@php
    $cursoDestacado = \App\Shared\Support\Institucion::cursos()[0] ?? null;
@endphp

<div {{ $attributes->class(['relative mx-auto aspect-square w-full max-w-[440px] select-none lg:max-w-[520px]']) }} aria-hidden="true">
    <svg viewBox="0 0 520 520" class="absolute inset-0 h-full w-full" fill="none">
        <defs>
            <linearGradient id="arco-360-degradado" x1="60" y1="120" x2="460" y2="420" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="rgb(var(--e360-primary))" />
                <stop offset="1" stop-color="rgb(var(--e360-secondary))" />
            </linearGradient>
        </defs>
        {{-- Disco de fondo, muy suave, para separar la composición del blanco. --}}
        <circle cx="260" cy="260" r="176" fill="rgb(var(--e360-primary-tint))" />
        {{-- Anillo interior punteado: profundidad, sin peso. --}}
        <circle cx="260" cy="260" r="206" stroke="rgb(var(--e360-secondary) / 0.18)" stroke-width="1.5" stroke-dasharray="3 9" stroke-linecap="round" />
        {{-- Arco principal (se anima con .arco-360, ver app.css). --}}
        <circle cx="260" cy="260" r="236" pathLength="1" stroke="url(#arco-360-degradado)" stroke-width="4" stroke-linecap="round" class="arco-360" />
        {{-- Punto naranja en el extremo del arco (320° desde las 3 en punto, sentido horario). --}}
        <circle cx="440.8" cy="108.3" r="8" fill="rgb(var(--e360-accent))" class="arco-360-punto" />
        <circle cx="440.8" cy="108.3" r="15" stroke="rgb(var(--e360-accent) / 0.25)" stroke-width="2" class="arco-360-punto" />
    </svg>

    {{-- Tarjeta central: un curso real del catálogo. --}}
    <div class="absolute left-1/2 top-1/2 w-[64%] -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-2xl border border-e360-border bg-white shadow-[0_24px_60px_-24px_rgb(var(--e360-heading)/0.35)]">
        <div class="relative flex aspect-[16/8] items-center justify-center bg-e360-secondary-tint">
            <x-landing.marca-360 :dot="false" class="absolute -right-6 -top-8 h-32 w-32 text-e360-secondary/20" />
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-e360-secondary-dark shadow-sm">
                <x-dynamic-component :component="'heroicon-o-'.($cursoDestacado['icono'] ?? 'academic-cap')" class="h-6 w-6" />
            </div>
            <span class="absolute left-3 top-3 rounded-full bg-white/95 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-e360-primary-deep">
                {{ $cursoDestacado['categoria'] ?? 'Curso' }}
            </span>
        </div>
        <div class="p-4">
            <p class="font-brand text-sm font-bold leading-snug text-e360-heading sm:text-[15px]">{{ $cursoDestacado['nombre'] ?? 'Curso' }}</p>
            <div class="mt-3 flex items-center justify-between gap-2">
                <p class="flex items-center gap-1.5 text-[11px] font-medium text-e360-muted">
                    <x-heroicon-o-computer-desktop class="h-4 w-4 text-e360-primary-dark" />
                    Modalidad virtual
                </p>
                <span class="rounded-md bg-e360-primary-deep px-2.5 py-1 text-[11px] font-semibold text-white">Ver curso</span>
            </div>
        </div>
    </div>

    {{-- Chip: acceso desde cualquier lugar. --}}
    <div class="absolute left-[2%] top-[14%] flex items-center gap-3 rounded-xl border border-e360-border bg-white px-3.5 py-2.5 shadow-lg shadow-e360-heading/10 sm:left-[4%]">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-e360-primary-tint text-e360-primary-deep">
            <x-heroicon-o-globe-alt class="h-5 w-5" />
        </span>
        <span class="leading-tight">
            <span class="block text-xs font-bold text-e360-heading">Aula virtual</span>
            <span class="block text-[11px] text-e360-muted">Desde cualquier lugar</span>
        </span>
    </div>

    {{-- Chip: formación por competencias. --}}
    <div class="absolute bottom-[5%] right-[2%] flex items-center gap-3 sm:bottom-[12%] rounded-xl border border-e360-border bg-white px-3.5 py-2.5 shadow-lg shadow-e360-heading/10 sm:right-[4%]">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-e360-accent-tint text-e360-accent-deep">
            <x-heroicon-o-check-badge class="h-5 w-5" />
        </span>
        <span class="leading-tight">
            <span class="block text-xs font-bold text-e360-heading">Por competencias</span>
            <span class="block text-[11px] text-e360-muted">Orientado al mundo laboral</span>
        </span>
    </div>
</div>
