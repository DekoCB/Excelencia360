<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable: los cargos adicionales ya existentes (y los que se sigan
 * creando sin indicarla) nunca cuentan como "vencidos" -- esta columna
 * es opcional a propósito, igual que CargoAdicional::$concepto es
 * texto libre caso por caso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargos_adicionales', function (Blueprint $table) {
            $table->date('fecha_vencimiento')->nullable()->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('cargos_adicionales', function (Blueprint $table) {
            $table->dropColumn('fecha_vencimiento');
        });
    }
};
