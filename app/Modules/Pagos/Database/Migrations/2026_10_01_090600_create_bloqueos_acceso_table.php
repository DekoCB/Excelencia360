<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloqueos_acceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->string('motivo', 255);
            $table->date('fecha_bloqueo');
            $table->date('fecha_desbloqueo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['estudiante_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloqueos_acceso');
    }
};
