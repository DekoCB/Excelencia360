<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/**
 * El picker por horario y la pantalla de crear/calificar se retiraron: las
 * evaluaciones ahora viven dentro de Cursos Virtuales (ver
 * aula-virtual.evaluacion). La libreta -- un resumen por ciclo que cruza
 * TODOS los cursos del estudiante, no uno solo -- sigue siendo su propia
 * pantalla porque no tiene un curso virtual al cual pertenecer.
 */
Route::middleware(['auth'])->prefix('evaluaciones')->name('evaluaciones.')->group(function () {
    Volt::route('libreta/{estudiante}/{ciclo}', 'evaluaciones.libreta')->name('libreta');

    Volt::route('mi-libreta', 'evaluaciones.mi-libreta')->name('mi-libreta');
});
