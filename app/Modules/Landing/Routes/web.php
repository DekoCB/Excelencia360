<?php

declare(strict_types=1);

use Livewire\Volt\Volt;

Volt::route('/', 'landing.index')->name('landing');
Volt::route('/cursos/{slug}', 'landing.curso')->name('landing.curso');
