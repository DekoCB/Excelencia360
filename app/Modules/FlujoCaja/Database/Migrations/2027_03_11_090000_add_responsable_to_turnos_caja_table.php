<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quien abre el turno (usuario_id, logueado) no siempre es quien queda a
 * cargo de la caja físicamente -- por ejemplo, un administrativo abre el
 * turno pero el cajero real es otra persona. Nullable: los turnos ya
 * abiertos/cerrados antes de este cambio no tenían este dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turnos_caja', function (Blueprint $table) {
            $table->string('responsable', 150)->nullable()->after('turno');
        });
    }

    public function down(): void
    {
        Schema::table('turnos_caja', function (Blueprint $table) {
            $table->dropColumn('responsable');
        });
    }
};
