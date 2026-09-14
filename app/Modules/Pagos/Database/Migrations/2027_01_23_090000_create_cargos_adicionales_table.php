<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobros futuros puntuales por estudiante (Convalidación, Exoneración,
 * Recuperación, Visación, etc.) -- a diferencia de Mensualidad, estos no
 * tienen un catálogo compartido: cada uno tiene su propio concepto (texto
 * libre) y monto, editables caso por caso al matricular. No están ligados a
 * PlanPago/Cuota (eso es solo para la mensualidad recurrente) ni a
 * Matricula, sino directo a Estudiante -- igual que Pago::estudiante_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos_adicionales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->string('concepto', 100);
            $table->decimal('monto', 8, 2);
            $table->string('estado', 15)->default('pendiente');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estudiante_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos_adicionales');
    }
};
