@props(['class' => ''])

{{--
    Mascota original del sistema: astronauta flotando, ilustración propia
    (SVG a mano, sin depender de ningún activo externo ni personaje de
    terceros). Degradados para dar volumen al casco/traje, y una insignia
    violeta en el pecho que lo liga a la paleta del Design System en vez de
    colores sueltos sin relación con la marca. $gradId evita que los
    degradados choquen entre sí cuando el componente aparece más de una vez
    en la misma página (hero-banner + apartado del sidebar a la vez).
--}}
@php
    $gradId = 'mascot-'.\Illuminate\Support\Str::random(6);
@endphp

<svg
    viewBox="0 0 200 230"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    {{ $attributes->merge(['class' => $class]) }}
    aria-hidden="true"
>
    <defs>
        <radialGradient id="{{ $gradId }}-visor" cx="38%" cy="32%" r="75%">
            <stop offset="0%" stop-color="#7C9BFF" />
            <stop offset="55%" stop-color="#3B4CA0" />
            <stop offset="100%" stop-color="#232F6B" />
        </radialGradient>
        <linearGradient id="{{ $gradId }}-suit" x1="30%" y1="0%" x2="75%" y2="100%">
            <stop offset="0%" stop-color="#FFFFFF" />
            <stop offset="100%" stop-color="#DDE3EE" />
        </linearGradient>
    </defs>

    <!-- Chispas y placa/bandera flotante -->
    <circle cx="26" cy="46" r="3.5" fill="#FBBF24" opacity="0.9" />
    <circle cx="22" cy="150" r="3" fill="#F8FAFC" opacity="0.7" />
    <path d="M170 34 l3 7 l7 3 l-7 3 l-3 7 l-3 -7 l-7 -3 l7 -3 Z" fill="#FBBF24" opacity="0.9" />

    <g transform="translate(148 16) rotate(18)">
        <rect x="0" y="0" width="30" height="30" rx="7" fill="#7C4DFF" />
        <path d="M9 7 V23 M9 7 H20 C23 7 23 13 20 13 H9" stroke="#F8FAFC" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" fill="none" />
    </g>

    <!-- Mochila -->
    <rect x="40" y="96" width="26" height="56" rx="10" fill="#7C4DFF" />

    <!-- Brazo izquierdo (en reposo) -->
    <path d="M66 128 C48 136 40 154 46 174" stroke="#E5E9F0" stroke-width="16" stroke-linecap="round" />
    <circle cx="45" cy="178" r="10" fill="url(#{{ $gradId }}-suit)" stroke="#B8C0D0" stroke-width="3" />

    <!-- Brazo derecho (saludando) -->
    <path d="M138 126 C158 110 166 82 154 60" stroke="#E5E9F0" stroke-width="16" stroke-linecap="round" />
    <circle cx="153" cy="54" r="10" fill="url(#{{ $gradId }}-suit)" stroke="#B8C0D0" stroke-width="3" />
    <rect x="146" y="46" width="14" height="10" rx="3" fill="#7C4DFF" />

    <!-- Cuerpo (traje) -->
    <path
        d="M100 92 C130 92 144 116 140 150 C137 178 122 196 100 202 C78 196 63 178 60 150 C56 116 70 92 100 92 Z"
        fill="url(#{{ $gradId }}-suit)"
        stroke="#C7CEDB"
        stroke-width="2"
    />
    <rect x="82" y="146" width="36" height="14" rx="7" fill="#1E1B33" />
    <path d="M100 148.5 L102.3 152.8 L107 153.4 L103.5 156.5 L104.5 161 L100 158.6 L95.5 161 L96.5 156.5 L93 153.4 L97.7 152.8 Z" fill="#C4B5FD" />

    <!-- Piernas -->
    <rect x="78" y="192" width="18" height="24" rx="8" fill="url(#{{ $gradId }}-suit)" stroke="#C7CEDB" stroke-width="2" />
    <rect x="104" y="192" width="18" height="24" rx="8" fill="url(#{{ $gradId }}-suit)" stroke="#C7CEDB" stroke-width="2" />

    <!-- Casco -->
    <circle cx="100" cy="62" r="42" fill="url(#{{ $gradId }}-suit)" stroke="#C7CEDB" stroke-width="3" />
    <circle cx="100" cy="62" r="32" fill="url(#{{ $gradId }}-visor)" />
    <path d="M78 46 C88 36 112 36 122 46 C112 54 88 54 78 46 Z" fill="#FFFFFF" opacity="0.4" />
    <circle cx="88" cy="60" r="4" fill="#0B1026" />
    <circle cx="112" cy="60" r="4" fill="#0B1026" />
    <path d="M90 72 C95 77 105 77 110 72" stroke="#0B1026" stroke-width="3.5" stroke-linecap="round" fill="none" />
</svg>
