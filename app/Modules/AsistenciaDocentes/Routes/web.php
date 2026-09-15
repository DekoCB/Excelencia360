<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Una sola página, distinta según permisos: quien tiene
// asistencia_docentes.registrar marca el día de todos los docentes; quien
// solo tiene asistencia_docentes.ver_propio ve su propio historial.
Route::middleware(['auth'])->prefix('asistencia-docentes')->name('asistencia-docentes.')->group(function () {
    Volt::route('/', 'asistencia-docentes.index')->name('index');
});
