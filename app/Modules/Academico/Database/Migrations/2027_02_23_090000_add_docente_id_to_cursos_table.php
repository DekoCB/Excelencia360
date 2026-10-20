<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solo tiene sentido para tipo=capacitacion: un curso académico asigna su
 * docente vía Horario (que varía por ciclo), pero un curso corto no tiene
 * Horario -- necesita su propio docente fijo para que Aula Virtual (punto 9
 * del backlog) sepa quién administra su contenido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->foreignId('docente_id')->nullable()->after('documento_autorizacion')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('docente_id');
        });
    }
};
