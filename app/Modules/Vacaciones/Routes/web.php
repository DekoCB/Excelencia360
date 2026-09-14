<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('vacaciones')->name('vacaciones.')->group(function () {
    Volt::route('/', 'vacaciones.index')->middleware('can:vacaciones.ver')->name('index');
});
