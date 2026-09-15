<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Una sola página con navegación por mes; el alcance de clases/evaluaciones
// que ve cada usuario lo decide CalendarioService::itemsDelMes() según su
// rol, igual que "Mis evaluaciones". Crear/editar/eliminar eventos puntuales
// (reuniones, actos, feriados) requiere calendario.gestionar.
Route::middleware(['auth'])->prefix('calendario')->name('calendario.')->group(function () {
    Volt::route('/', 'calendario.index')->name('index');
});
