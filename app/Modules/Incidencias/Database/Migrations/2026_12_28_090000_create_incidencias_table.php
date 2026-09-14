<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('reportado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('horario_id')->nullable()->constrained('horarios')->nullOnDelete();
            $table->foreignId('tarea_id')->nullable()->constrained('tareas')->nullOnDelete();
            $table->foreignId('evaluacion_id')->nullable()->constrained('evaluaciones')->nullOnDelete();
            $table->string('tipo', 30);
            $table->text('descripcion');
            $table->date('fecha');
            $table->dateTime('notificado_apoderado_en')->nullable();
            $table->timestamps();

            $table->index(['estudiante_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias');
    }
};
