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
            // Independiente de horario_id: la sección (Grupo A/B) de la
            // matrícula ya no se infiere de a qué horario apunta horario_id,
            // sino que se guarda directamente aquí. horario_id queda como
            // una referencia aparte (ver 2026_12_20_090100), editable sin
            // afectar a cuál sección pertenece el estudiante.
            $table->string('seccion', 10)->nullable()->after('horario_id');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropColumn('seccion');
        });
    }
};
