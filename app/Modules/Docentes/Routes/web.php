<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('docentes')->name('docentes.')->group(function () {
    Volt::route('/', 'docentes.index')
        ->middleware('can:docentes.ver')
        ->name('index');

    // Antes de {docente} por el mismo motivo que en matricula/Routes/web.php:
    // un segmento comodín capturaría esta ruta literal si se registrara después.
    Volt::route('carga-masiva', 'docentes.carga-masiva')
        ->middleware('can:docentes.gestionar')
        ->name('carga-masiva');
});

Route::middleware(['auth'])->prefix('contratos')->name('contratos.')->group(function () {
    Volt::route('/', 'contratos.index')
        ->middleware('can:contratos.ver')
        ->name('index');
});
