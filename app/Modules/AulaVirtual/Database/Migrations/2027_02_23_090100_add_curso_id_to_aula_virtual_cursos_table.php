<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un aula virtual de curso corto (punto 9 del backlog) no tiene Horario --
 * se ancla directamente al Curso (tipo=capacitacion) en vez de a un
 * horario_id. horario_id pasa a nullable en vez de agregar una tabla
 * paralela; CursoVirtual queda con exactamente uno de los dos set (nunca
 * ambos, nunca ninguno), invariante que valida CursoVirtualService en vez
 * de un CHECK de base de datos, siguiendo la convención del resto del
 * proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aula_virtual_cursos', function (Blueprint $table) {
            $table->foreignId('horario_id')->nullable()->change();
            $table->foreignId('curso_id')->nullable()->unique()->after('horario_id')
                ->constrained('cursos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aula_virtual_cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curso_id');
            $table->foreignId('horario_id')->nullable(false)->change();
        });
    }
};
