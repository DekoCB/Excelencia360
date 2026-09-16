<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "semana" nunca fue más que decorativo aquí (ver EvaluacionService)
 * -- Evaluacion ya tiene una fecha real, a diferencia de
 * Material/ClaseGrabada/Tarea/Foro que no tenían ninguna hasta que se
 * agregó Seccion (ver create_secciones_table). Se elimina en vez de
 * migrarse a Seccion porque Evaluacion pertenece al módulo Evaluaciones,
 * no a Aula Virtual (Seccion es curso_virtual_id, no horario_id) --
 * unificar ambos módulos bajo el mismo concepto de sección queda para
 * cuando se mueva Evaluaciones dentro de Cursos Virtuales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropColumn('semana');
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->unsignedSmallInteger('semana')->nullable();
        });
    }
};
