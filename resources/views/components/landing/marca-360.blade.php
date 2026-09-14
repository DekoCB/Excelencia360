@props(['dot' => true])

{{--
    Motivo gráfico de la marca: un anillo casi completo (los "360°") que
    termina en el punto naranja. Se usa como viñeta de los antetítulos y, a
    gran escala y sin el punto (dot=false), como decoración de fondo en las
    tarjetas. Hereda el color del texto (currentColor).
--}}
<svg viewBox="0 0 20 20" fill="none" aria-hidden="true" {{ $attributes }}>
    <path d="M15.66 4.34A8 8 0 1 0 18 10" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" />
    @if ($dot)
        <circle cx="17.2" cy="6.2" r="1.9" fill="rgb(var(--e360-accent))" />
    @endif
</svg>
