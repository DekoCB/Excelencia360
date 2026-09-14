<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas_curso_virtual')->cascadeOnDelete();
            $table->unsignedInteger('semana')->nullable();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('puntaje_max')->default(20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_tareas');
    }
};
