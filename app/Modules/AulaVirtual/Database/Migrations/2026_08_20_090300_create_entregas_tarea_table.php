<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas_tarea', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->text('comentario')->nullable();
            $table->dateTime('fecha_entrega')->nullable();
            $table->decimal('nota', 4, 2)->nullable();
            $table->string('estado', 15)->default('pendiente');
            $table->timestamps();

            $table->unique(['tarea_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas_tarea');
    }
};
