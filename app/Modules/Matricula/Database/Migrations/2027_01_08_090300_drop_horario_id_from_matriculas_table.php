<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * matriculas.horario_id era un solo valor "de referencia" que no
 * distinguía secciones/paralelos del mismo curso (ver migración que lo
 * creó). Ahora que la asignación real vive en matricula_horario (una fila
 * por curso, ver create_matricula_horario_table y la migración de datos
 * anterior), esta columna sobra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('horario_id');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->foreignId('horario_id')->nullable()->after('grado_id')->constrained('horarios')->nullOnDelete();
        });
    }
};
