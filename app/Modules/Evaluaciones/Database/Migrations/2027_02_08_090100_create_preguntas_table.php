<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preguntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->text('enunciado');
            $table->decimal('puntaje', 5, 2);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['evaluacion_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preguntas');
    }
};
