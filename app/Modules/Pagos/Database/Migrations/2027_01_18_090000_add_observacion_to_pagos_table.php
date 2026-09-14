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
            // Distinto de "detalle" (que solo especifica qué es un concepto
            // "Otro" y solo se pide en ese caso): esta es una nota libre y
            // opcional para cualquier pago, que se imprime en el recibo.
            $table->text('observacion')->nullable()->after('detalle');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('observacion');
        });
    }
};
