<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El motor de notas nuevo (Criterios por Unidad, Examen Final,
 * Sustitutorio, Aplazado, Asistencia -- ver RolEvaluacionEnum) reutiliza
 * Evaluacion/Calificacion en vez de modelos nuevos: cada Criterio,
 * Examen Final, etc. es una Evaluacion con su propio rol (y, solo para
 * Criterio, su Unidad 1 o 2). Las evaluaciones ya existentes antes de
 * este cambio quedan con rol/unidad null -- no participan de la nueva
 * fórmula hasta que el docente registre sus notas bajo el esquema nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->string('rol', 20)->nullable()->after('tipo');
            $table->unsignedTinyInteger('unidad')->nullable()->after('rol');
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropColumn(['rol', 'unidad']);
        });
    }
};
