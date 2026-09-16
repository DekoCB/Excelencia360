<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas_estudiante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregunta_id')->constrained('preguntas')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->json('alternativas_elegidas')->nullable();
            $table->text('texto_respuesta')->nullable();
            $table->decimal('puntaje_obtenido', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['pregunta_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_estudiante');
    }
};
