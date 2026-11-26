<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable: los turnos ya abiertos/cerrados antes de este cambio no
 * tenían franja -- se quedan sin ella en vez de forzar un valor
 * inventado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos_caja', function (Blueprint $table) {
            $table->string('turno', 10)->nullable()->after('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::table('turnos_caja', function (Blueprint $table) {
            $table->dropColumn('turno');
        });
    }
};
