<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// biblioteca.index: catálogo + (si biblioteca.gestionar) préstamos activos
// y las acciones de prestar/devolver/marcar perdido. biblioteca.mis-prestamos:
// historial propio de quien solo tiene biblioteca.ver_propio.
Route::middleware(['auth'])->prefix('biblioteca')->name('biblioteca.')->group(function () {
    Volt::route('/', 'biblioteca.index')->name('index');
    Volt::route('/mis-prestamos', 'biblioteca.mis-prestamos')->name('mis-prestamos');
});
