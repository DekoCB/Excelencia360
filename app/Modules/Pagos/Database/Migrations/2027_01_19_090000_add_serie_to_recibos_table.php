<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->string('serie', 3)->default('001')->after('pago_id');
        });

        // El correlativo ahora es por serie (dos talonarios independientes,
        // no una copia automática del mismo número): numero_recibo por sí
        // solo ya no es único, la pareja serie+numero_recibo sí.
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropUnique(['numero_recibo']);
            $table->unique(['serie', 'numero_recibo']);
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropUnique(['serie', 'numero_recibo']);
            $table->unique('numero_recibo');
        });

        Schema::table('recibos', function (Blueprint $table) {
            $table->dropColumn('serie');
        });
    }
};
