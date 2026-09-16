<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intentos_evaluacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->timestamp('enviado_en');
            $table->timestamp('calificado_en')->nullable();
            $table->timestamps();

            $table->unique(['evaluacion_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intentos_evaluacion');
    }
};
