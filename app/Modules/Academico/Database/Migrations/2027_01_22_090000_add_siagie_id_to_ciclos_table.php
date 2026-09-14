<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            // Solo se usa en el (a lo sumo un) Ciclo modalidad=anual de cada
            // año: lo vincula al Siagie tipo=anual que lo representa, para
            // que Vacaciones/Evaluaciones puedan consultar el modelo nuevo
            // (Ciclo::siagie) en vez de comparar Ciclo::modalidad a mano.
            $table->foreignId('siagie_id')->nullable()->after('estado')->constrained('siagies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('siagie_id');
        });
    }
};
