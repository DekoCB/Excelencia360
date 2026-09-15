<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TramiteService::todos() filtra por categoria de forma independiente al
 * estado -- el índice compuesto (estado, created_at) que ya existía no
 * sirve para ese filtro solo (regla del prefijo izquierdo de los índices
 * compuestos). Encontrado en la pasada de optimización (fase 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_tramite', function (Blueprint $table) {
            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_tramite', function (Blueprint $table) {
            $table->dropIndex(['categoria']);
        });
    }
};
