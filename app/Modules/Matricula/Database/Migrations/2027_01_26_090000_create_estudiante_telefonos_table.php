<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Celulares adicionales de un estudiante mayor de edad, aparte del
 * celular principal (estudiantes.celular, que no se toca). Es aditivo:
 * cualquier lugar del sistema que ya use estudiantes.celular sigue
 * funcionando igual, esto es solo una lista de números extra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiante_telefonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->string('numero', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante_telefonos');
    }
};
