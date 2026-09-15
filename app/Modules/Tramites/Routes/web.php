<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Una sola página, distinta según permisos (igual que Incidencias): quien
// solo puede crear/ver lo propio ve su lista y el formulario de nueva
// solicitud; quien puede gestionar ve todas, con filtros y acciones de
// estado.
Route::middleware(['auth'])->prefix('tramites')->name('tramites.')->group(function () {
    Volt::route('/', 'tramites.index')->name('index');
});
