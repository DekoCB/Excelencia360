@props(['nombre', 'subtitulo' => 'Aquí tienes un resumen de lo que está activo hoy en '.config('institucion.nombre_corto').'.'])

{{--
    Panel de saludo del Dashboard: degradado propio (ver .dashboard-hero-gradient
    en app.css), no ligado al tema claro/oscuro -- el texto siempre es blanco.
    La mascota vive FUERA de la caja con el degradado (que sí recorta sus
    propias esquinas con overflow-hidden) para poder asomar por encima del
    borde superior en vez de quedar cortada por el radio -- por eso el
    wrapper exterior no tiene overflow-hidden y la caja del degradado es un
    div aparte. El wrapper lleva un margen superior propio (no el space-y-6
    del contenedor del dashboard) para darle a la mascota aire suficiente y
    que no la recorte el contenedor con scroll del layout (main > overflow-y-auto).
--}}
<div class="relative mt-8 sm:mt-10">
    <div class="dashboard-hero-gradient relative overflow-hidden rounded-2xl px-6 py-7 shadow-lg sm:px-8">
        <div class="relative z-10 max-w-lg">
            <p class="font-display text-2xl font-medium text-white sm:text-3xl">¡Hola, {{ $nombre }}! 👋</p>
            <p class="mt-2 text-sm text-white/75">{{ $subtitulo }}</p>
        </div>
    </div>

    <img
        src="{{ asset('images/pet.png') }}"
        alt=""
        class="pointer-events-none absolute -top-8 right-4 z-10 hidden h-32 w-32 rotate-6 object-contain drop-shadow-2xl sm:-top-10 sm:right-6 sm:block sm:h-40 sm:w-40 md:-top-12 md:right-8 md:h-48 md:w-48"
    >
</div>
