<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('turno_caja_id')->nullable()->after('registrado_por')->constrained('turnos_caja')->nullOnDelete();
        });

        Schema::table('egresos', function (Blueprint $table) {
            $table->foreignId('turno_caja_id')->nullable()->after('registrado_por')->constrained('turnos_caja')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turno_caja_id');
        });

        Schema::table('egresos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turno_caja_id');
        });
    }
};
