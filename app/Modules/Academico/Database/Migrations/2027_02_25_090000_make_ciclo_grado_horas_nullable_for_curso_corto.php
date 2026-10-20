<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un Horario de curso corto no tiene Período de matrícula ni Semestre (no
 * aplica, ver la modalidad Curso Corto): ciclo_id y grado_id pasan a
 * nullable -- null en ambos es la señal de "es un horario de curso
 * corto", igual que CursoVirtual::esDeCursoCorto() usa horario_id null.
 * Las horas de clase también pasan a opcionales para curso corto: un
 * horario puede quedar con solo los días marcados, sin hora_inicio ni
 * hora_fin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            $table->foreignId('ciclo_id')->nullable()->change();
            $table->foreignId('grado_id')->nullable()->change();
        });

        Schema::table('horario_dias', function (Blueprint $table) {
            $table->time('hora_inicio')->nullable()->change();
            $table->time('hora_fin')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('horario_dias', function (Blueprint $table) {
            $table->time('hora_inicio')->nullable(false)->change();
            $table->time('hora_fin')->nullable(false)->change();
        });

        Schema::table('horarios', function (Blueprint $table) {
            $table->foreignId('ciclo_id')->nullable(false)->change();
            $table->foreignId('grado_id')->nullable(false)->change();
        });
    }
};
