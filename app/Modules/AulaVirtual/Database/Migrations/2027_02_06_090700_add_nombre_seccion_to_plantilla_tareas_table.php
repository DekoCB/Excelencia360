<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A diferencia de las otras 3 plantillas, plantilla_tareas SÍ conserva
 * "semana": ahí no es una etiqueta de sección sino un desplazamiento (N
 * semanas desde el inicio del ciclo) para calcular una fecha_limite
 * razonable al aplicar la plantilla a un ciclo nuevo -- ver
 * PlantillaCursoVirtualService::aplicar(). nombre_seccion es un campo
 * aparte, con el mismo propósito que en las otras 3 plantillas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_tareas', function (Blueprint $table) {
            $table->string('nombre_seccion', 100)->nullable()->after('plantilla_id');
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_tareas', function (Blueprint $table) {
            $table->dropColumn('nombre_seccion');
        });
    }
};
