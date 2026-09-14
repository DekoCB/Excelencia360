@props(['variant' => 'neutral'])

{{--
    Píldora de estado global -- reemplaza los `<span class="rounded-full ...">`
    sueltos que había en cada página, con padding/tamaño distintos entre sí
    (ver auditoría del Design System). `variant` mapea a los tokens
    semánticos ya usados en el resto del sistema.
--}}
@php
    $estilos = [
        'ok' => 'bg-ok/10 text-ok',
        'warn' => 'bg-warn/10 text-warn',
        'danger' => 'bg-danger/10 text-danger',
        'info' => 'bg-info/10 text-info',
        'accent' => 'bg-accent-soft text-accent',
        'neutral' => 'bg-ink-faint/10 text-ink-dim',
    ];

    $estilo = $estilos[$variant] ?? $estilos['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {$estilo}"]) }}>
    {{ $slot }}
</span>
