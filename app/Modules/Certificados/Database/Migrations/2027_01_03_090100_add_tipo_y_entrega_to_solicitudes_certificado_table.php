<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_certificado', function (Blueprint $table) {
            $table->string('tipo', 30)->default('certificado_estudios')->after('estudiante_id');
            $table->foreignId('libreta_id')->nullable()->after('certificado_id')->constrained('libretas')->nullOnDelete();
            $table->string('metodo_entrega', 10)->nullable()->after('motivo_rechazo');
            $table->string('correo_entrega', 150)->nullable()->after('metodo_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificado', function (Blueprint $table) {
            $table->dropColumn(['correo_entrega', 'metodo_entrega', 'tipo']);
            $table->dropConstrainedForeignId('libreta_id');
        });
    }
};
