@props(['variant' => 'ok'])

{{--
    Banner de mensaje flash global -- reemplaza los banners de
    `session('status')` hechos a mano en cada página (ver auditoría del
    Design System: ~27 sitios, con deriva de tag/clases entre varios).
    `variant` mapea a los tokens semánticos ya usados en el resto del sistema.
--}}
@php
    $estilos = [
        'ok' => 'border-ok/30 bg-ok/10 text-ok',
        'warn' => 'border-warn/30 bg-warn/10 text-warn',
        'danger' => 'border-danger/30 bg-danger/10 text-danger',
        'info' => 'border-info/30 bg-info/10 text-info',
    ];

    $estilo = $estilos[$variant] ?? $estilos['ok'];
@endphp

<div {{ $attributes->merge(['class' => "rounded-md border px-4 py-3 text-sm {$estilo}"]) }}>
    {{ $slot }}
</div>
