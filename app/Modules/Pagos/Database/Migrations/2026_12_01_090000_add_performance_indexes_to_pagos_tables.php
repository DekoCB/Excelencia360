<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para los patrones de consulta reales del módulo (ver
 * BloqueoAccesoService::cuotasVencidasDe() y PagoService), que combinan
 * estas columnas en el WHERE sin tener hasta ahora un índice que las cubra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->index(['estado', 'fecha_vencimiento']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->index(['cuota_id', 'estado']);
            $table->index('estado');
        });

        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->index('activa');
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropIndex(['estado', 'fecha_vencimiento']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex(['cuota_id', 'estado']);
            $table->dropIndex(['estado']);
        });

        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropIndex(['activa']);
        });
    }
};
