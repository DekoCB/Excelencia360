@php
    // $title llega desde #[Title] o $view->title() del componente Volt;
    // $metaDescription desde $view->layoutData() (página de curso).
    $tituloPagina = $title ?? 'EXCELENCIA 360 | Formación y Capacitación';
    $descripcionPagina = $metaDescription
        ?? 'Grupo Excelencia 360: formación y capacitación en diferentes áreas mediante una modalidad virtual accesible y orientada a una educación de calidad competitiva.';
    $favicon = \App\Shared\Support\Institucion::faviconUrl();
    $faviconTipo = str_ends_with($favicon, '.svg') ? 'image/svg+xml' : 'image/png';
    $imagenSocial = \App\Shared\Support\Institucion::logoUrl();
@endphp
<!DOCTYPE html>
<html lang="es" class="landing-page">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $tituloPagina }}</title>
        <meta name="description" content="{{ $descripcionPagina }}">
        <link rel="canonical" href="{{ url()->current() }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('institucion.nombre') }}">
        <meta property="og:title" content="{{ $tituloPagina }}">
        <meta property="og:description" content="{{ $descripcionPagina }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:locale" content="es_PE">
        @if ($imagenSocial)
            <meta property="og:image" content="{{ $imagenSocial }}">
        @endif
        <meta name="twitter:card" content="summary">
        <meta name="theme-color" content="#18BEBC">

        <link rel="icon" type="{{ $faviconTipo }}" href="{{ $favicon }}">

        <!-- Fonts: Plus Jakarta Sans para títulos, Inter para texto -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|plus-jakarta-sans:600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <a href="#contenido" class="landing-skip-link">Saltar al contenido</a>
        {{ $slot }}
    </body>
</html>
