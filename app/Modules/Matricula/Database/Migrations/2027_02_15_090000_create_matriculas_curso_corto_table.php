<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matrícula de "curso corto": un estudiante que solo lleva un curso de
 * capacitación individual (tipo=capacitacion en `cursos`) por un tiempo más
 * corto, sin semestre ni programa de estudio -- a propósito, un modelo
 * aparte y liviano, sin relación con `matriculas`/`ciclos`/`grados`, para
 * no tocar nada de ese flujo (ver plan de implementación). Un estudiante
 * puede tener varios cursos cortos, incluso a la vez, así que no hay
 * unique sobre estudiante_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculas_curso_corto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 15)->default('activo');
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas_curso_corto');
    }
};
