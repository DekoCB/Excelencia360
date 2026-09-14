<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_cambio_monto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concepto_pago_id')->constrained('conceptos_pago')->cascadeOnDelete();
            $table->decimal('monto_actual', 8, 2);
            $table->decimal('monto_propuesto', 8, 2);
            $table->string('estado', 15)->default('pendiente');
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_solicitud');
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_resolucion')->nullable();
            $table->string('motivo_rechazo', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cambio_monto');
    }
};
