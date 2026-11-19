<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tres campos nuevos de matrícula, confirmados con el cliente:
 * - tipo_matricula: Regular/Convalidación (ver TipoMatriculaEnum) -- solo
 *   etiqueta, no cambia ninguna regla de matriculación.
 * - modalidad_estudio: Distancia/Presencial/Semipresencial (ver
 *   ModalidadEstudioEnum), elegida por el propio estudiante al
 *   matricularse -- no afecta al Aula Virtual.
 * - seccion_academica_id: la Sección (A-E) dentro de su Programa de
 *   Estudio -- ver secciones_academicas, puramente de reporte/filtro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->string('tipo_matricula', 20)->default('regular')->after('estado');
            $table->string('modalidad_estudio', 20)->nullable()->after('tipo_matricula');
            $table->foreignId('seccion_academica_id')->nullable()->after('modalidad_estudio')
                ->constrained('secciones_academicas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seccion_academica_id');
            $table->dropColumn(['tipo_matricula', 'modalidad_estudio']);
        });
    }
};
