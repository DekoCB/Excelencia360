<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('academico')->name('academico.')->group(function () {
    Volt::route('grados', 'academico.grados.index')
        ->middleware('can:academico.ver')
        ->name('grados.index');

    Volt::route('aulas', 'academico.aulas.index')
        ->middleware('can:academico.ver')
        ->name('aulas.index');

    Volt::route('cursos', 'academico.cursos.index')
        ->middleware('can:academico.ver')
        ->name('cursos.index');

    Volt::route('ciclos', 'academico.ciclos.index')
        ->middleware('can:academico.ver')
        ->name('ciclos.index');

    Volt::route('siagie', 'academico.siagie.index')
        ->middleware('can:academico.ver')
        ->name('siagie.index');

    Volt::route('ciclos/{ciclo}', 'academico.ciclos.show')
        ->middleware('can:academico.ver')
        ->name('ciclos.show');

    Volt::route('horarios', 'academico.horarios.index')
        ->middleware('can:academico.ver')
        ->name('horarios.index');
});
