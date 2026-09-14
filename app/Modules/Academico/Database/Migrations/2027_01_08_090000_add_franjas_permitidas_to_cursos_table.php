<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            // Lista de valores de FranjaHorarioEnum en los que se puede
            // dictar este curso. null (o vacío) = sin restricción, se
            // permiten las 3 -- es solo una preferencia del curso, no crea
            // el Horario en sí (eso se sigue haciendo en el módulo Horarios).
            $table->json('franjas_permitidas')->nullable()->after('grado_id');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('franjas_permitidas');
        });
    }
};
