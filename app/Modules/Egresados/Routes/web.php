<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('egresados')->name('egresados.')->group(function () {
    Volt::route('/', 'egresados.index')->middleware('can:egresados.ver')->name('index');
});
