<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('ciclo_id')->constrained('ciclos')->restrictOnDelete();
            $table->foreignId('grado_id')->constrained('grados')->restrictOnDelete();
            $table->date('fecha_matricula');
            $table->string('estado', 15)->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['estudiante_id', 'ciclo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};
