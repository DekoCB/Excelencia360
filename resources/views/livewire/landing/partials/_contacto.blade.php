@php
    $datosContacto = array_filter([
        ['icono' => 'building-office-2', 'etiqueta' => 'Institución', 'valor' => mb_strtoupper($institucion['nombre'])],
        ['icono' => 'identification', 'etiqueta' => 'RUC', 'valor' => $institucion['ruc']],
        ['icono' => 'map-pin', 'etiqueta' => 'Dirección', 'valor' => $institucion['direccion']],
        ['icono' => 'globe-americas', 'etiqueta' => 'Departamento', 'valor' => $institucion['ciudad'].', '.$institucion['pais']],
        ['icono' => 'user-circle', 'etiqueta' => 'Gerente General', 'valor' => $institucion['gerente_general']],
        ['icono' => 'phone', 'etiqueta' => 'Teléfono', 'valor' => $institucion['telefono']],
        ['icono' => 'envelope', 'etiqueta' => 'Correo', 'valor' => $institucion['email']],
    ], fn (array $dato) => filled($dato['valor']));
@endphp

<section id="contacto" class="bg-e360-surface py-20 sm:py-24 lg:py-28" aria-labelledby="contacto-titulo">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title eyebrow="Contacto" subtitle="¿Tienes dudas sobre un curso o un servicio? Escríbenos y te respondemos.">
            <span id="contacto-titulo">Contáctanos</span>
        </x-landing.section-title>

        <div class="mx-auto grid max-w-6xl grid-cols-1 gap-8 lg:grid-cols-5">
            <div x-data x-reveal class="lg:col-span-2">
                <div class="rounded-2xl border border-e360-border bg-white p-6 shadow-sm shadow-e360-heading/5 sm:p-8">
                    <h3 class="font-brand text-xl font-bold text-e360-heading">Datos institucionales</h3>
                    <dl class="mt-5 space-y-4">
                        @foreach ($datosContacto as $dato)
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-e360-primary-tint text-e360-primary-deep">
                                    <x-dynamic-component :component="'heroicon-o-'.$dato['icono']" class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-e360-muted">{{ $dato['etiqueta'] }}</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-e360-heading">{{ $dato['valor'] }}</dd>
                                </div>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>

            <div x-data x-reveal.120 class="lg:col-span-3">
                <x-landing.contact-form :asuntos="$asuntos" :enviado="$enviado" :error-envio="$errorEnvio" :errors="$errors" />
            </div>
        </div>
    </div>
</section>
