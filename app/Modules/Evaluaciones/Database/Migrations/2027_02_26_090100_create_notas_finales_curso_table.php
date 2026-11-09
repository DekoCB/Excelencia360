<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota final de un estudiante en un curso (Horario), editada a mano por el
 * docente -- gana sobre el promedio calculado de las evaluaciones
 * (LibretaService::resumenPorCursos()) en cuanto existe una fila aquí.
 * Mientras no exista, la boleta sigue mostrando el promedio calculado
 * como siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_finales_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('horario_id')->constrained('horarios')->cascadeOnDelete();
            $table->decimal('nota', 4, 2);
            $table->foreignId('editado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['estudiante_id', 'horario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_finales_curso');
    }
};
