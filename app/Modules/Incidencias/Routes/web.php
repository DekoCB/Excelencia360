<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('incidencias')->name('incidencias.')->group(function () {
    Volt::route('/', 'incidencias.index')->name('index');
});
