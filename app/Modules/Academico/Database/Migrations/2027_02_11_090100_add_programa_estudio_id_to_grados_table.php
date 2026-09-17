<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grados', function (Blueprint $table) {
            $table->foreignId('programa_estudio_id')->nullable()->after('id')->constrained('programas_estudio')->restrictOnDelete();
        });

        $programaId = DB::table('programas_estudio')->value('id');
        DB::table('grados')->update(['programa_estudio_id' => $programaId]);

        // La columna queda nullable a nivel de esquema (el proyecto no tiene
        // doctrine/dbal instalado, requisito de Blueprint::change() para
        // endurecerla a NOT NULL); lo obligatorio se exige en
        // GradoService/el formulario, mismo criterio ya usado en otras
        // columnas "siempre presentes en la práctica" de este proyecto.
        Schema::table('grados', function (Blueprint $table) {
            $table->dropUnique(['orden']);
            $table->unique(['programa_estudio_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::table('grados', function (Blueprint $table) {
            $table->dropUnique(['programa_estudio_id', 'orden']);
            $table->dropForeign(['programa_estudio_id']);
            $table->dropColumn('programa_estudio_id');
            $table->unique('orden');
        });
    }
};
