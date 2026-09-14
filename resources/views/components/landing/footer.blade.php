@php
    $inicio = route('landing');
    $navegacion = [
        ['href' => $inicio, 'etiqueta' => 'Inicio'],
        ['href' => $inicio.'#conocenos', 'etiqueta' => 'Conócenos'],
        ['href' => $inicio.'#cursos', 'etiqueta' => 'Cursos'],
        ['href' => $inicio.'#servicios', 'etiqueta' => 'Servicios'],
        ['href' => $inicio.'#blog', 'etiqueta' => 'Blog'],
        ['href' => $inicio.'#contacto', 'etiqueta' => 'Contacto'],
    ];
    // Redes sociales: solo se listan las que tengan URL en config/institucion.php.
    $redes = array_filter((array) config('institucion.redes', []));
    $etiquetasRedes = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x' => 'X'];
    $claseEnlace = 'text-sm text-e360-text transition-colors hover:text-e360-primary-deep focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-e360-secondary';
@endphp

<footer class="border-t border-e360-border bg-e360-surface">
    <div class="linea-marca h-[3px]" aria-hidden="true"></div>

    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-16">
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">
            <div class="sm:col-span-2 lg:col-span-1">
                <x-brand-logo size="md" />
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-e360-text">
                    Institución de formación y capacitación en diferentes áreas, en modalidad virtual, orientada a una educación de calidad competitiva.
                </p>
            </div>

            <nav aria-label="Pie de página">
                <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-e360-heading">Navegación</h2>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($navegacion as $enlace)
                        <li><a href="{{ $enlace['href'] }}" class="{{ $claseEnlace }}">{{ $enlace['etiqueta'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <div>
                <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-e360-heading">Información institucional</h2>
                <ul class="mt-4 space-y-3 text-sm text-e360-text">
                    <li class="flex items-start gap-2.5">
                        <x-heroicon-o-identification class="mt-0.5 h-4 w-4 shrink-0 text-e360-primary-dark" aria-hidden="true" />
                        <span>RUC {{ config('institucion.ruc') }}</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <x-heroicon-o-map-pin class="mt-0.5 h-4 w-4 shrink-0 text-e360-primary-dark" aria-hidden="true" />
                        <span>{{ config('institucion.direccion') }}</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <x-heroicon-o-globe-americas class="mt-0.5 h-4 w-4 shrink-0 text-e360-primary-dark" aria-hidden="true" />
                        <span>{{ config('institucion.ciudad') }}, {{ config('institucion.pais') }}</span>
                    </li>
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-e360-heading">Contacto</h2>
                <ul class="mt-4 space-y-3 text-sm text-e360-text">
                    <li class="flex items-start gap-2.5">
                        <x-heroicon-o-envelope class="mt-0.5 h-4 w-4 shrink-0 text-e360-primary-dark" aria-hidden="true" />
                        <a href="{{ $inicio }}#contacto" class="{{ $claseEnlace }}">Escríbenos desde el formulario</a>
                    </li>
                    @if (config('institucion.telefono'))
                        <li class="flex items-start gap-2.5">
                            <x-heroicon-o-phone class="mt-0.5 h-4 w-4 shrink-0 text-e360-primary-dark" aria-hidden="true" />
                            <a href="tel:{{ preg_replace('/\s+/', '', config('institucion.telefono')) }}" class="{{ $claseEnlace }}">{{ config('institucion.telefono') }}</a>
                        </li>
                    @endif
                    @if (config('institucion.email'))
                        <li class="flex items-start gap-2.5">
                            <x-heroicon-o-at-symbol class="mt-0.5 h-4 w-4 shrink-0 text-e360-primary-dark" aria-hidden="true" />
                            <a href="mailto:{{ config('institucion.email') }}" class="{{ $claseEnlace }} break-all">{{ config('institucion.email') }}</a>
                        </li>
                    @endif
                    @foreach ($redes as $red => $url)
                        <li class="flex items-start gap-2.5">
                            <x-heroicon-o-arrow-top-right-on-square class="mt-0.5 h-4 w-4 shrink-0 text-e360-primary-dark" aria-hidden="true" />
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="{{ $claseEnlace }}">{{ $etiquetasRedes[$red] ?? ucfirst($red) }}</a>
                        </li>
                    @endforeach
                    <li class="flex items-start gap-2.5">
                        <x-heroicon-o-lock-closed class="mt-0.5 h-4 w-4 shrink-0 text-e360-primary-dark" aria-hidden="true" />
                        <a href="{{ route('login') }}" wire:navigate class="{{ $claseEnlace }}">Iniciar sesión</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-2 border-t border-e360-border pt-6 text-xs text-e360-muted sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ now()->year }} {{ config('institucion.nombre') }}. Todos los derechos reservados.</p>
            <p>{{ config('institucion.razon_social') }} · RUC {{ config('institucion.ruc') }}</p>
        </div>
    </div>
</footer>
