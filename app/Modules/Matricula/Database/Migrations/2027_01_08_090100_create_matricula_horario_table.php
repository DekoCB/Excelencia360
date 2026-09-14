<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matricula_horario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->foreignId('horario_id')->constrained('horarios')->cascadeOnDelete();
            $table->timestamps();

            // Un estudiante puede tener a lo más un horario asignado por
            // curso, pero eso se exige en el Service (no aquí a nivel de
            // esquema, porque el curso no vive en esta tabla) -- esta
            // restricción solo evita duplicar la MISMA fila dos veces.
            $table->unique(['matricula_id', 'horario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matricula_horario');
    }
};
