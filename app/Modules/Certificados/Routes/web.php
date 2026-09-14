<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('certificados')->name('certificados.')->group(function () {
    Volt::route('/', 'certificados.index')->name('index');

    Volt::route('mis-certificados', 'certificados.mis-certificados')->name('mis-certificados');
});

// Constancias comparte el mismo backend que Certificados (Certificado,
// SolicitudCertificado, PlantillaCertificado, todos ya distinguidos por
// TipoDocumentoEnum) pero es una pantalla propia en el sidebar, bajo
// "Trámites" junto a Certificados -- ver TipoDocumentoEnum::constancias().
Route::middleware(['auth'])->prefix('constancias')->name('constancias.')->group(function () {
    Volt::route('/', 'constancias.index')->name('index');

    Volt::route('mis-constancias', 'constancias.mis-constancias')->name('mis-constancias');
});

// Verificación pública: sin autenticación, para que terceros (empleadores,
// otras instituciones) validen un certificado a partir de su código impreso.
Volt::route('verificar-certificado', 'certificados.verificar')->name('certificados.verificar');
