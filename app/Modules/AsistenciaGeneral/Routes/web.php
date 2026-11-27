<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('asistencia-general')->name('asistencia-general.')->group(function () {
    Volt::route('/', 'asistencia-general.index')->name('index');
});
