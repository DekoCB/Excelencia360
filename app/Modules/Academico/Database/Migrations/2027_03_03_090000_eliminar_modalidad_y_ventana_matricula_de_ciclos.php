<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los "Grupos rotativos de 6 meses" y el "Periodo académico anual" no
 * existen en el instituto real -- se quita esa distinción por completo
 * (columnas, no solo UI): los periodos académicos pasan a tener solo
 * nombre, año y fechas libres. La ventana de matrícula propia del ciclo
 * también desaparece: con el nuevo módulo de Matrícula, el personal
 * elige el periodo libremente en cualquier momento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->dropColumn(['modalidad', 'tipo', 'fecha_inicio_matricula', 'fecha_fin_matricula', 'estado_matricula']);
        });
    }

    public function down(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->string('tipo', 15)->nullable()->after('nombre');
            $table->string('modalidad', 15)->default('seis_meses')->after('tipo');
            $table->date('fecha_inicio_matricula')->nullable()->after('estado');
            $table->date('fecha_fin_matricula')->nullable()->after('fecha_inicio_matricula');
            $table->string('estado_matricula', 10)->nullable()->after('fecha_fin_matricula');
        });
    }
};
