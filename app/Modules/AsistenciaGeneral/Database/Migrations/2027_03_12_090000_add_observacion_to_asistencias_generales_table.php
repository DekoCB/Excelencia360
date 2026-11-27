<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anotación libre del registro -- usada hoy para dejar constancia de que
 * se registró a mano porque la persona olvidó su fotocheck (en vez de
 * escanear su QR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencias_generales', function (Blueprint $table) {
            $table->string('observacion', 255)->nullable()->after('hora_salida');
        });
    }

    public function down(): void
    {
        Schema::table('asistencias_generales', function (Blueprint $table) {
            $table->dropColumn('observacion');
        });
    }
};
