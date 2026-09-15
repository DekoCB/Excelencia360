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

    // Portal de Apoderados: también antes de {estudiante} por la misma
    // razón que las de arriba.
    Volt::route('mis-hijos', 'matricula.mis-hijos')
        ->middleware('can:matricula.ver_propio_hijo')
        ->name('mis-hijos');

    Volt::route('{estudiante}', 'matricula.show')
        ->middleware('can:matricula.ver')
        ->name('show');
});
