<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('flujo-caja')->name('flujo-caja.')->group(function () {
    Volt::route('/', 'flujo-caja.index')->middleware('can:flujo_caja.ver')->name('index');
});
