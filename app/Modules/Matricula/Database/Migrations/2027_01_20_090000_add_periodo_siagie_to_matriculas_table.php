<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            // Independiente del Grupo (Ciclo): ver PeriodoSiagieEnum.
            // Nullable porque no todas las matrículas anteriores a este
            // campo tienen un periodo SIAGIE registrado a mano todavía.
            $table->string('periodo_siagie', 10)->nullable()->after('grado_id');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropColumn('periodo_siagie');
        });
    }
};
