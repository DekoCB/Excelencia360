<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('notificaciones')->name('notificaciones.')->group(function () {
    Volt::route('/', 'notificaciones.index')->name('index');

    Volt::route('mis-mensajes', 'notificaciones.mis-mensajes')->name('mis-mensajes');

    Volt::route('plantillas', 'notificaciones.plantillas')->name('plantillas');
});
