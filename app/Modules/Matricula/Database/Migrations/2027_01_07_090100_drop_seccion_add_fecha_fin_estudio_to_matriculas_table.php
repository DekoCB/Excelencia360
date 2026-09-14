<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            // La sección (Grupo A/B) ya no es un dato propio de la
            // matrícula: la determina el grado (grados 1-2 = Aula A,
            // grados 3-4 = Aula B), siempre igual dentro de un mismo
            // Grupo/Ciclo -- ver Grado::letraAula().
            $table->dropColumn('seccion');

            // Fecha en que culmina el periodo de estudio de ESTE
            // estudiante en particular: se calcula al matricular como
            // fecha_matricula + 6 meses (mayores) u 8 meses (menores),
            // independiente de cuándo cierra el Ciclo/Grupo compartido, y
            // queda editable desde la ficha.
            $table->date('fecha_fin_estudio')->nullable()->after('fecha_matricula');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->string('seccion', 10)->nullable()->after('horario_id');
            $table->dropColumn('fecha_fin_estudio');
        });
    }
};
