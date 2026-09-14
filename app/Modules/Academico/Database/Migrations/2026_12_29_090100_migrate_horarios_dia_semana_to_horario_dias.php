<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convierte cada horario existente (una franja fija: domingo/lun_mie/mar_jue
 * con un solo horario) en sus filas equivalentes de horario_dias, un día
 * real por fila, con el mismo horario que tenía. No se usa el enum
 * DiaSemanaEnum aquí a propósito: sus casos van a cambiar de franjas a días
 * sueltos en el mismo cambio, así que esta migración de datos usa los
 * valores crudos que existían en la base al momento de escribirse.
 */
return new class extends Migration
{
    private const MAPA_FRANJAS = [
        'domingo' => ['domingo'],
        'lun_mie' => ['lunes', 'miercoles'],
        'mar_jue' => ['martes', 'jueves'],
    ];

    public function up(): void
    {
        $horarios = DB::table('horarios')->select('id', 'dia_semana', 'hora_inicio', 'hora_fin')->get();

        $ahora = now();

        foreach ($horarios as $horario) {
            $dias = self::MAPA_FRANJAS[$horario->dia_semana] ?? [$horario->dia_semana];

            foreach ($dias as $dia) {
                DB::table('horario_dias')->insert([
                    'horario_id' => $horario->id,
                    'dia_semana' => $dia,
                    'hora_inicio' => $horario->hora_inicio,
                    'hora_fin' => $horario->hora_fin,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Los datos vuelven a leerse desde horarios.dia_semana/hora_inicio/
        // hora_fin (todavía no se han borrado en este punto del down()), así
        // que basta con vaciar horario_dias.
        DB::table('horario_dias')->truncate();
    }
};
