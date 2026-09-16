<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las plantillas no tienen curso virtual real (se aplican a uno nuevo
 * cada vez, ver PlantillaCursoVirtualService::aplicar()), así que no
 * pueden apuntar a una Sección real con fecha propia -- solo guardan el
 * NOMBRE de la sección de origen, para recrear (o reutilizar, si ya
 * existe una con ese nombre) una Sección real en el curso destino.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_materiales', function (Blueprint $table) {
            $table->string('nombre_seccion', 100)->nullable()->after('plantilla_id');
            $table->dropColumn('semana');
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_materiales', function (Blueprint $table) {
            $table->dropColumn('nombre_seccion');
            $table->unsignedInteger('semana')->nullable();
        });
    }
};
