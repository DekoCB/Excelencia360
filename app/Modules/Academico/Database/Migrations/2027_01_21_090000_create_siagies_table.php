<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siagies', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10);
            $table->unsignedSmallInteger('anio');
            // Obligatorias solo para tipo=anual (tiene Ciclo propio con
            // horarios reales, ver Siagie::ciclo()); 1.er/2.° periodo son
            // clasificación pura sin fechas propias.
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 15)->default('planificado');
            $table->timestamps();

            $table->unique(['tipo', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siagies');
    }
};
