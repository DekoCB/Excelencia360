<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sección académica es puramente una etiqueta de reporte/filtro (A-E por
 * ahora, ampliable desde el panel sin tocar código) para agrupar
 * estudiantes dentro de un Programa de Estudio -- no determina aula ni
 * horario, a diferencia de la antigua "Sección A/B" que se eliminó de
 * `horarios`/`matriculas` (ver 2027_01_07_090000/090100_drop_seccion_*).
 *
 * Nombre de tabla distinto de "secciones" a propósito: ese nombre ya lo
 * usa AulaVirtual\Models\Seccion para los bloques de contenido del aula
 * virtual (Bienvenida, Fin de curso...), un concepto completamente
 * distinto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secciones_academicas', function (Blueprint $table) {
            $table->id();
            $table->string('letra', 10)->unique();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        $ahora = now();

        DB::table('secciones_academicas')->insert(
            collect(['A', 'B', 'C', 'D', 'E'])
                ->map(fn (string $letra) => [
                    'letra' => $letra,
                    'activa' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])
                ->all(),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('secciones_academicas');
    }
};
