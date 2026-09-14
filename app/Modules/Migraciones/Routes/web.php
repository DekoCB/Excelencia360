<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('migraciones')->name('migraciones.')->group(function () {
    Volt::route('/', 'migraciones.index')->middleware('can:migraciones.ver')->name('index');
});
