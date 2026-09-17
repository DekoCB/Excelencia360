<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('matricula')->name('matricula.')->group(function () {
    Volt::route('/', 'matricula.index')
        ->middleware('can:matricula.ver')
        ->name('index');

    // Rutas de carga masiva: van antes de {estudiante} porque, al ser un
    // segmento comodín, capturaría estas rutas literales si se registrara
    // primero.
    Volt::route('carga-masiva/estudiantes', 'matricula.carga-masiva-estudiantes')
        ->middleware('can:matricula.crear')
        ->name('carga-masiva-estudiantes');

    Volt::route('carga-masiva', 'matricula.carga-masiva')
        ->middleware('can:matricula.crear')
        ->name('carga-masiva');

    // Portal de Apoderados / directorio de tutores: también antes de
    // {estudiante} por la misma razón que las de arriba. Sin middleware
    // "can" propio porque acepta dos permisos distintos según el modo
    // (apoderado vs. directorio de staff) -- el propio componente valida
    // eso en mount(), ver matricula/mis-hijos.blade.php.
    Volt::route('mis-hijos', 'matricula.mis-hijos')
        ->name('mis-hijos');

    Volt::route('apoderados', 'matricula.apoderados.index')
        ->middleware('can:matricula.ver')
        ->name('apoderados.index');

    Volt::route('{estudiante}', 'matricula.show')
        ->middleware('can:matricula.ver')
        ->name('show');
});
