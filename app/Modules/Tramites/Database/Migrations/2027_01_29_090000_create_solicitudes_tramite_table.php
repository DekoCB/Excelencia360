<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FUT (Formulario Único de Trámite): cualquier miembro de la institución
 * (estudiante, apoderado, docente, personal) puede presentar una
 * solicitud administrativa genérica -- a diferencia de
 * SolicitudCertificado o SolicitudCambioMonto, que ya resuelven un caso
 * puntual cada una, este es el canal para todo lo demás. La trazabilidad
 * de quién cambió qué y cuándo la cubre el trait Auditable (AuditLog) ya
 * usado en el resto del sistema, no una tabla de historial aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_tramite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitante_id')->constrained('users')->cascadeOnDelete();
            $table->string('categoria', 20);
            $table->string('asunto', 150);
            $table->text('descripcion');
            $table->string('estado', 20)->default('registrada');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolucion')->nullable();
            $table->dateTime('atendido_en')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_tramite');
    }
};
