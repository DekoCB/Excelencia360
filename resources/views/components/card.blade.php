@props(['title' => null])

{{--
    Base compartida para las tarjetas de contenido de todo el sistema
    (reemplaza el antiguo patrón repetido "rounded-lg border border-border
    bg-surface"). El padding y el resto de utilidades de layout (p-4,
    lg:col-span-2, etc.) los sigue poniendo quien la usa vía $attributes,
    igual que antes -- este componente solo fija el radio/sombra comunes.
    El slot "header" es para las tarjetas tipo lista (Notificaciones,
    Actividad reciente, etc.) que ya traían su propia barra de título.
--}}
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-border bg-surface shadow-sm']) }}>
    @if ($title || isset($header))
        <div class="border-b border-border px-4 py-3">
            @isset($header)
                {{ $header }}
            @else
                <h2 class="text-sm font-semibold text-ink">{{ $title }}</h2>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
