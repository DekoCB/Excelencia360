<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->foreignId('curso_capacitacion_id')->nullable()->after('matricula_id')
                ->constrained('cursos_capacitacion')->nullOnDelete();
            // Indexado pero no único, mismo criterio que codigo_verificacion:
            // un duplicado (ver CertificadoService::duplicar()) reutiliza el
            // numero_registro del original a propósito, para que ambos
            // verifiquen igual -- verificar() ya filtra es_duplicado=false,
            // así que nunca hay ambigüedad de a cuál devolver.
            $table->string('numero_registro', 20)->nullable()->index()->after('curso_capacitacion_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->dropColumn('numero_registro');
            $table->dropConstrainedForeignId('curso_capacitacion_id');
        });
    }
};
