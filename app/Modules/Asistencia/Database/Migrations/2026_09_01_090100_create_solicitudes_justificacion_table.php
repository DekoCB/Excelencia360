<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_justificacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asistencia_id')->unique()->constrained('asistencias')->cascadeOnDelete();
            $table->string('motivo', 255);
            $table->string('estado', 15)->default('pendiente');
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->string('motivo_rechazo', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_justificacion');
    }
};
