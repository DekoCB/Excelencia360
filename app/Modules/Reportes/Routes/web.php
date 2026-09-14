<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('reportes')->name('reportes.')->group(function () {
    Volt::route('/', 'reportes.index')->name('index');
});

Route::middleware(['auth'])->prefix('historial-estudiante')->name('historial-estudiante.')->group(function () {
    Volt::route('/', 'historial-estudiante.index')->name('index');
});
