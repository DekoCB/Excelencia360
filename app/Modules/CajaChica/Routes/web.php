<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('caja-chica')->name('caja-chica.')->group(function () {
    Volt::route('/', 'caja-chica.index')->name('index');
});
