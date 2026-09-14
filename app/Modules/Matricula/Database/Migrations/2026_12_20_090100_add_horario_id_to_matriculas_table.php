<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            // Nullable: un grado con un solo horario no obliga a elegir
            // sección, y las matrículas existentes antes de este cambio
            // quedan sin horario específico asignado (siguen apareciendo en
            // todas las secciones de su grado, como ya funcionaba antes).
            $table->foreignId('horario_id')->nullable()->after('grado_id')->constrained('horarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('horario_id');
        });
    }
};
