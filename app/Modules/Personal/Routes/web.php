<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('personal')->name('personal.')->group(function () {
    Volt::route('/', 'personal.index')
        ->middleware('can:personal.ver')
        ->name('index');

    // Antes de {personal} por el mismo motivo que en docentes/Routes/web.php:
    // un segmento comodín capturaría esta ruta literal si se registrara después.
    Volt::route('carga-masiva', 'personal.carga-masiva')
        ->middleware('can:personal.gestionar')
        ->name('carga-masiva');
});
