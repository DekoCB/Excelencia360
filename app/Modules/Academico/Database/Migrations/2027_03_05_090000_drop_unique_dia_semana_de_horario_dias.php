<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un mismo Horario (mismo curso/docente/aula) ahora puede tener más de un
 * bloque de horas el mismo día de la semana (ej. lunes mañana y lunes
 * tarde) -- antes este unique lo impedía incluso cuando los dos bloques no
 * se cruzaban en horas. La tabla ya tiene `id` autoincremental como PK
 * real, así que no hace falta ningún otro cambio de esquema; el
 * no-solape entre bloques del mismo horario ahora lo valida
 * HorarioService::validarDias().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horario_dias', function (Blueprint $table) {
            $table->dropUnique(['horario_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::table('horario_dias', function (Blueprint $table) {
            $table->unique(['horario_id', 'dia_semana']);
        });
    }
};
