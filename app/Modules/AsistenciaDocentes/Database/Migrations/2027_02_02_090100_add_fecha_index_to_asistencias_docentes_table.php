<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AsistenciaDocenteService::deDia() filtra solo por fecha, pero el único
 * índice existente es el compuesto único (docente_id, fecha) -- fecha va
 * segundo ahí, así que una búsqueda solo por fecha no puede usarlo (regla
 * del prefijo izquierdo). Encontrado en la pasada de optimización (fase 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencias_docentes', function (Blueprint $table) {
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('asistencias_docentes', function (Blueprint $table) {
            $table->dropIndex(['fecha']);
        });
    }
};
