<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control de ingreso/salida del Instituto completo (estudiantes y
 * personal, con o sin cuenta de usuario) -- "persona" es polimórfico
 * porque cubre tres modelos sin relación entre sí: Estudiante, User
 * (personal con login) y Personal (portería, limpieza, etc., sin login),
 * mismo patrón que AuditLog::auditable().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias_generales', function (Blueprint $table) {
            $table->id();
            $table->string('persona_type');
            $table->unsignedBigInteger('persona_id');
            $table->date('fecha');
            $table->string('turno', 10);
            $table->dateTime('hora_ingreso');
            $table->dateTime('hora_salida')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['persona_type', 'persona_id']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias_generales');
    }
};
