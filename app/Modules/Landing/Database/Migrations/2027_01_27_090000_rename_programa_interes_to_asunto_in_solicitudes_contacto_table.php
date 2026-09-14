<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El formulario de contacto de Excelencia 360 pide un "Asunto" (curso,
 * servicio o consulta general) en lugar del "programa de interés" de la
 * web anterior. Se renombra la columna para conservar las solicitudes ya
 * recibidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_contacto', function (Blueprint $table) {
            $table->renameColumn('programa_interes', 'asunto');
        });

        Schema::table('solicitudes_contacto', function (Blueprint $table) {
            $table->string('asunto', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_contacto', function (Blueprint $table) {
            $table->string('asunto', 100)->nullable()->change();
        });

        Schema::table('solicitudes_contacto', function (Blueprint $table) {
            $table->renameColumn('asunto', 'programa_interes');
        });
    }
};
