<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('docente_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('aula_id')->constrained('aulas')->restrictOnDelete();
            $table->foreignId('ciclo_id')->constrained('ciclos')->cascadeOnDelete();
            $table->foreignId('grado_id')->constrained('grados')->restrictOnDelete();
            $table->string('dia_semana', 10);
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            $table->index(['aula_id', 'dia_semana', 'ciclo_id']);
            $table->index(['docente_id', 'dia_semana', 'ciclo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
