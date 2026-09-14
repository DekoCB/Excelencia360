<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('evaluaciones')->name('evaluaciones.')->group(function () {
    Volt::route('/', 'evaluaciones.index')->name('index');

    Volt::route('libreta/{estudiante}/{ciclo}', 'evaluaciones.libreta')->name('libreta');

    Volt::route('mi-libreta', 'evaluaciones.mi-libreta')->name('mi-libreta');

    Volt::route('{horario}', 'evaluaciones.show')->name('show');
});
