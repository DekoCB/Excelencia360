<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dia_semana/hora_inicio/hora_fin ya viven en horario_dias (un horario
 * puede dictarse varios días, cada uno con su propio horario); estas
 * columnas quedan redundantes tras la migración de datos anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            // Los índices compuestos que se borran abajo respaldan hoy las
            // llaves foráneas de aula_id/docente_id; MySQL no deja borrarlos
            // sin un índice de reemplazo, así que estos se crean primero.
            $table->index('aula_id');
            $table->index('docente_id');
        });

        Schema::table('horarios', function (Blueprint $table) {
            $table->dropIndex(['aula_id', 'dia_semana', 'ciclo_id']);
            $table->dropIndex(['docente_id', 'dia_semana', 'ciclo_id']);
            $table->dropColumn(['dia_semana', 'hora_inicio', 'hora_fin']);
        });
    }

    public function down(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            $table->string('dia_semana', 10)->after('seccion');
            $table->time('hora_inicio')->after('dia_semana');
            $table->time('hora_fin')->after('hora_inicio');
        });

        Schema::table('horarios', function (Blueprint $table) {
            $table->dropIndex(['aula_id']);
            $table->dropIndex(['docente_id']);
            $table->index(['aula_id', 'dia_semana', 'ciclo_id']);
            $table->index(['docente_id', 'dia_semana', 'ciclo_id']);
        });
    }
};
