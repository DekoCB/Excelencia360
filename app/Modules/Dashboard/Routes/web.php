<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->group(function () {
    Volt::route('dashboard', 'dashboard.index')->name('dashboard');
});
