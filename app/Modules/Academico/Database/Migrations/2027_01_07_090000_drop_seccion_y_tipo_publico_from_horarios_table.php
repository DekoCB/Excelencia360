<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            // El Grupo A/B ya no es una etiqueta libre por horario: lo
            // determina el aula asignada (Aula A = grados 1-2, Aula B =
            // grados 3-4), siempre igual dentro de un mismo Grupo/Ciclo.
            // Con eso, tipo_publico tampoco tiene trabajo que hacer: la
            // edad ya no restringe a qué horario/aula pertenece un
            // estudiante, solo afecta su fecha_fin_estudio calculada (ver
            // matriculas.fecha_fin_estudio).
            $table->dropColumn(['seccion', 'tipo_publico']);
        });
    }

    public function down(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            $table->string('seccion', 10)->nullable();
            $table->string('tipo_publico', 10)->nullable();
        });
    }
};
