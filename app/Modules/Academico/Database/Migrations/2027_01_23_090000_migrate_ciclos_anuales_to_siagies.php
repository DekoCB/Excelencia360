<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cada Ciclo modalidad=anual existente pasa a tener su propio Siagie
     * tipo=anual (mismas fechas/estado), y queda vinculado vía
     * ciclos.siagie_id. El Ciclo en sí NO se borra ni se mueve: sigue
     * siendo el contenedor real de Horarios/Matrículas de esos estudiantes
     * -- solo deja de aparecer en el listado de Grupos (ver
     * CicloService::listar()) porque ahora tiene un Siagie asociado.
     */
    public function up(): void
    {
        // Corrige el nombre mal escrito ("SIAGE") que haya quedado guardado
        // en ciclos creados antes de la corrección del código -- renombrar
        // el texto en el código no alcanza a las filas que ya existían.
        DB::statement("UPDATE ciclos SET nombre = REPLACE(nombre, 'SIAGE', 'SIAGIE') WHERE nombre LIKE '%SIAGE%'");

        $ciclosAnuales = DB::table('ciclos')->where('modalidad', 'anual')->get();

        foreach ($ciclosAnuales as $ciclo) {
            $siagieId = DB::table('siagies')->where('tipo', 'anual')->where('anio', $ciclo->anio)->value('id');

            if ($siagieId === null) {
                $siagieId = DB::table('siagies')->insertGetId([
                    'tipo' => 'anual',
                    'anio' => $ciclo->anio,
                    'fecha_inicio' => $ciclo->fecha_inicio,
                    'fecha_fin' => $ciclo->fecha_fin,
                    'estado' => $ciclo->estado,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('ciclos')->where('id', $ciclo->id)->update(['siagie_id' => $siagieId]);
        }
    }

    public function down(): void
    {
        DB::table('ciclos')->where('modalidad', 'anual')->update(['siagie_id' => null]);
        DB::table('siagies')->where('tipo', 'anual')->delete();
    }
};
