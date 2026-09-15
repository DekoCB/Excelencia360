@props(['nombre', 'subtitulo' => 'Aquí tienes un resumen de lo que está activo hoy en '.config('institucion.nombre_corto').'.'])

{{--
    Panel de saludo del Dashboard: degradado propio (ver .dashboard-hero-gradient
    en app.css), no ligado al tema claro/oscuro -- el texto siempre es blanco.
    Sin margen superior propio: el padding de <main> (layouts/app.blade.php)
    ya separa el contenido de la barra de arriba, igual que el resto de
    páginas del panel -- el mt-8/mt-10 que tenía antes era solo para dejarle
    aire a la mascota que asomaba por encima (ya no está).
--}}
<div class="relative">
    <div class="dashboard-hero-gradient relative overflow-hidden rounded-2xl px-6 py-7 shadow-lg sm:px-8">
        <div class="relative z-10 max-w-lg">
            <p class="font-display text-2xl font-medium text-white sm:text-3xl">¡Hola, {{ $nombre }}! 👋</p>
            <p class="mt-2 text-sm text-white/75">{{ $subtitulo }}</p>
        </div>
    </div>
</div>
