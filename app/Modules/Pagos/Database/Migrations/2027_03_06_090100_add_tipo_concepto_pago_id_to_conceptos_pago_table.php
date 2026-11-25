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
        Schema::table('conceptos_pago', function (Blueprint $table) {
            $table->foreignId('tipo_concepto_pago_id')->nullable()->after('tipo')->constrained('tipos_concepto_pago')->restrictOnDelete();
        });

        // Backfill: cada ConceptoPago.tipo (string, valores del viejo enum)
        // ya coincide 1 a 1 con el slug recién sembrado en tipos_concepto_pago.
        $tiposPorSlug = DB::table('tipos_concepto_pago')->pluck('id', 'slug');

        foreach ($tiposPorSlug as $slug => $id) {
            DB::table('conceptos_pago')->where('tipo', $slug)->update(['tipo_concepto_pago_id' => $id]);
        }

        Schema::table('conceptos_pago', function (Blueprint $table) {
            $table->foreignId('tipo_concepto_pago_id')->nullable(false)->change();
            $table->dropColumn('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('conceptos_pago', function (Blueprint $table) {
            $table->string('tipo', 20)->nullable()->after('nombre');
        });

        $slugsPorId = DB::table('tipos_concepto_pago')->pluck('slug', 'id');

        foreach ($slugsPorId as $id => $slug) {
            DB::table('conceptos_pago')->where('tipo_concepto_pago_id', $id)->update(['tipo' => $slug]);
        }

        Schema::table('conceptos_pago', function (Blueprint $table) {
            $table->string('tipo', 20)->nullable(false)->change();
            $table->dropConstrainedForeignId('tipo_concepto_pago_id');
        });
    }
};
