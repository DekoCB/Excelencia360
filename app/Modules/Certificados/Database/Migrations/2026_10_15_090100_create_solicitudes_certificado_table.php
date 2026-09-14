<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_certificado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matriculas')->nullOnDelete();
            $table->text('motivo');
            $table->string('estado')->default('pendiente');
            $table->foreignId('atendido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('certificado_id')->nullable()->constrained('certificados')->nullOnDelete();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_certificado');
    }
};
