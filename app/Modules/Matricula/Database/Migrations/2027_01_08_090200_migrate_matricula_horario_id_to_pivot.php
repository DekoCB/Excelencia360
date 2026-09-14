<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copia cada matriculas.horario_id existente a una fila de la nueva tabla
 * pivote matricula_horario, antes de que la migración siguiente borre esa
 * columna. Corre después de create_matricula_horario_table.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        DB::table('matriculas')
            ->whereNotNull('horario_id')
            ->select('id', 'horario_id')
            ->orderBy('id')
            ->each(function (object $matricula) use ($ahora): void {
                DB::table('matricula_horario')->insert([
                    'matricula_id' => $matricula->id,
                    'horario_id' => $matricula->horario_id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            });
    }

    public function down(): void
    {
        // matriculas.horario_id todavía no se ha borrado en este punto del
        // down() (lo borra la migración siguiente), así que basta con
        // vaciar la tabla pivote.
        DB::table('matricula_horario')->truncate();
    }
};
