<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horario_dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horario_id')->constrained('horarios')->cascadeOnDelete();
            $table->string('dia_semana', 10);
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            $table->unique(['horario_id', 'dia_semana']);
            $table->index('dia_semana');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horario_dias');
    }
};
