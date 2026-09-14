<?php

/*
|--------------------------------------------------------------------------
| Identidad institucional
|--------------------------------------------------------------------------
|
| Única fuente de los datos de la institución que se muestran en la web
| pública, el login, el panel y los PDFs. Los textos de misión, visión y
| descripción son los oficiales entregados por la institución: no se
| reformulan aquí. Los campos en null son datos que todavía no existen
| (teléfono, correo, redes, precios de cursos...) y se muestran solo
| cuando se completen.
|
*/

return [

    'nombre' => 'Grupo Excelencia 360',
    'nombre_corto' => 'Excelencia 360',
    'razon_social' => 'Excelencia 360',
    'ruc' => '20614566133',
    'inicio_actividades' => '12 de agosto de 2025',
    'actividad' => 'Enseñanza superior',
    'direccion' => 'Mza. N Lote. 10 A.H. Simon Bolivar',
    'ciudad' => 'Puno',
    'pais' => 'Perú',
    'gerente_general' => 'Velasquez Maquera Ali',

    // Canales de contacto: se muestran solo si tienen valor.
    'telefono' => null,
    'email' => null,
    'redes' => [
        // 'facebook' => 'https://...',
        // 'instagram' => 'https://...',
        // 'linkedin' => 'https://...',
    ],

    /*
    | Logo institucional. Copiar el archivo a public/images/excelencia360/
    | y la marca aparece sola en navbar, footer, login, panel y PDFs;
    | mientras no exista se muestra una marca tipográfica provisional.
    */
    'logo' => 'images/excelencia360/logo.png',
    'favicon' => 'images/excelencia360/favicon.svg',

    'descripcion' => 'Es una institución de formación y capacitación de personas en diferentes áreas, '
        .'con una sólida base ética, capaces de actuar en el mundo laboral y comprometido con el '
        .'desarrollo del país, que se rigen por los principios de una educación de calidad competitiva.',

    'mision' => 'Capacitar en diferentes áreas en la modalidad virtual, promoviendo esta forma de '
        .'enseñanza para ser más accesibles a nuestros clientes y de esta manera desarrollar una '
        .'educación de calidad competitiva.',

    'vision' => 'Al 208 ser reconocidos como una de las principales Instituciones de mayor influencia '
        .'en formación y capacitación en la modalidad virtual en las diferentes áreas.',

    'valores' => [
        ['nombre' => 'Trabajo en equipo', 'icono' => 'user-group', 'descripcion' => 'Colaboramos y sumamos capacidades para lograr objetivos comunes.'],
        ['nombre' => 'Compromiso', 'icono' => 'hand-raised', 'descripcion' => 'Asumimos con responsabilidad lo que ofrecemos a cada estudiante.'],
        ['nombre' => 'Puntualidad', 'icono' => 'clock', 'descripcion' => 'Respetamos el tiempo: cumplimos plazos y horarios acordados.'],
        ['nombre' => 'Perseverancia', 'icono' => 'arrow-trending-up', 'descripcion' => 'Mantenemos el esfuerzo constante hasta alcanzar las metas.'],
        ['nombre' => 'Pasión', 'icono' => 'fire', 'descripcion' => 'Enseñamos con entusiasmo y dedicación por lo que hacemos.'],
        ['nombre' => 'Ética', 'icono' => 'scale', 'descripcion' => 'Actuamos con integridad, honestidad y respeto en todo momento.'],
    ],

    'propuesta_valor' => [
        ['titulo' => 'Formación de calidad', 'icono' => 'check-badge', 'descripcion' => 'Contenido orientado al desarrollo de competencias.'],
        ['titulo' => 'Modalidad virtual', 'icono' => 'computer-desktop', 'descripcion' => 'Aprendizaje accesible desde cualquier lugar.'],
        ['titulo' => 'Desarrollo profesional', 'icono' => 'briefcase', 'descripcion' => 'Formación orientada al mundo laboral.'],
        ['titulo' => 'Educación competitiva', 'icono' => 'trophy', 'descripcion' => 'Preparación basada en una educación de calidad competitiva.'],
    ],

    /*
    | Cursos. duracion, precio, certificacion, docente y horario quedan en
    | null hasta que la institución los defina; las tarjetas y la página
    | del curso solo muestran los campos con valor.
    */
    'cursos' => [
        [
            'slug' => 'ofimatica-aplicada-a-la-gestion-publica',
            'nombre' => 'Ofimática Aplicada a la Gestión Pública',
            'categoria' => 'Gestión pública',
            'icono' => 'computer-desktop',
            'descripcion' => 'Curso de ofimática aplicada a las tareas propias de la gestión pública.',
            'modalidad' => 'Virtual',
            'duracion' => null,
            'precio' => null,
            'certificacion' => null,
            'docente' => null,
            'horario' => null,
        ],
        [
            'slug' => 'herramientas-digitales-para-la-redaccion-cientifica',
            'nombre' => 'Herramientas digitales para la redacción científica',
            'subtitulo' => 'Artículos / Tesis',
            'categoria' => 'Investigación',
            'icono' => 'document-text',
            'descripcion' => 'Curso sobre herramientas digitales para la redacción científica de artículos y tesis.',
            'modalidad' => 'Virtual',
            'duracion' => null,
            'precio' => null,
            'certificacion' => null,
            'docente' => null,
            'horario' => null,
        ],
        [
            'slug' => 'curso-de-e-learning-para-docentes',
            'nombre' => 'Curso de E-learning para docentes',
            'categoria' => 'Docencia',
            'icono' => 'academic-cap',
            'descripcion' => 'Curso de e-learning dirigido a docentes.',
            'modalidad' => 'Virtual',
            'duracion' => null,
            'precio' => null,
            'certificacion' => null,
            'docente' => null,
            'horario' => null,
        ],
    ],

    // Descripciones genéricas derivadas solo del nombre de cada servicio.
    'servicios' => [
        ['nombre' => 'Capacitación en General', 'icono' => 'presentation-chart-line', 'descripcion' => 'Programas de capacitación en diferentes áreas, en modalidad virtual.'],
        ['nombre' => 'Consultoría', 'icono' => 'chat-bubble-left-right', 'descripcion' => 'Servicios de consultoría para organizaciones y personas.'],
        ['nombre' => 'Investigación', 'icono' => 'magnifying-glass', 'descripcion' => 'Servicios de apoyo a procesos de investigación.'],
        ['nombre' => 'Marketing Digital', 'icono' => 'megaphone', 'descripcion' => 'Servicios de marketing digital para tu organización o proyecto.'],
        ['nombre' => 'Asesoría Jurídica', 'icono' => 'scale', 'descripcion' => 'Servicios de asesoría en materia jurídica.'],
    ],

    /*
    | Publicaciones del blog. Vacío hasta que existan publicaciones reales;
    | la web muestra un estado vacío en lugar de contenido ficticio.
    | Estructura esperada por cada entrada: titulo, extracto, categoria,
    | fecha (Y-m-d), autor, imagen (ruta en public/) y url.
    */
    'blog' => [],

];
