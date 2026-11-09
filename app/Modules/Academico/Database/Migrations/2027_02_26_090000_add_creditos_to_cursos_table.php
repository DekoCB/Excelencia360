<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable a propósito: los cursos ya existentes no tienen créditos
 * cargados. Un curso sin créditos simplemente no entra al cálculo del
 * promedio ponderado de la boleta de notas (ver
 * LibretaService::datosBoletaCompleta()) hasta que alguien lo complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->unsignedSmallInteger('creditos')->nullable()->after('horas');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('creditos');
        });
    }
};
