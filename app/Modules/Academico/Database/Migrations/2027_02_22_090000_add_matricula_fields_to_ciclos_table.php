<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fusiona "Período de Matrícula" (antes un modelo hijo aparte,
 * PeriodoMatricula) dentro del propio Ciclo: en vez de una tabla con
 * varias ventanas posibles por ciclo, cada Ciclo tiene ahora su propia
 * ventana única de matrícula. Antes de dropear periodos_matricula (ver
 * la migración siguiente) se migran los datos existentes: si un ciclo
 * tenía varios periodos, se toma el rango que los cubre a todos (la
 * fecha de inicio más temprana y la de fin más tardía) y queda "abierto"
 * si al menos uno de ellos lo estaba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->date('fecha_inicio_matricula')->nullable()->after('estado');
            $table->date('fecha_fin_matricula')->nullable()->after('fecha_inicio_matricula');
            $table->string('estado_matricula', 10)->nullable()->after('fecha_fin_matricula');
        });

        $ciclos = DB::table('ciclos')->pluck('id');

        foreach ($ciclos as $cicloId) {
            $periodos = DB::table('periodos_matricula')->where('ciclo_id', $cicloId)->get();

            if ($periodos->isEmpty()) {
                continue;
            }

            DB::table('ciclos')->where('id', $cicloId)->update([
                'fecha_inicio_matricula' => $periodos->min('fecha_inicio'),
                'fecha_fin_matricula' => $periodos->max('fecha_fin'),
                'estado_matricula' => $periodos->contains('estado', 'abierto') ? 'abierto' : 'cerrado',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio_matricula', 'fecha_fin_matricula', 'estado_matricula']);
        });
    }
};
