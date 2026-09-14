<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('libretas', function (Blueprint $table) {
            $table->dateTime('entregado_en')->nullable()->after('generado_en');
            $table->foreignId('entregado_por')->nullable()->after('entregado_en')->constrained('users')->nullOnDelete();
            $table->string('metodo_entrega', 10)->nullable()->after('entregado_por');
            $table->string('correo_entrega', 150)->nullable()->after('metodo_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('libretas', function (Blueprint $table) {
            $table->dropColumn(['correo_entrega', 'metodo_entrega']);
            $table->dropConstrainedForeignId('entregado_por');
            $table->dropColumn('entregado_en');
        });
    }
};
