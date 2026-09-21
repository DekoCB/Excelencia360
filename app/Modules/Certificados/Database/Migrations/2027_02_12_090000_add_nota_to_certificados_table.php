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
            // Promedio ponderado final del curso de capacitación (escala
            // 0-20, igual que calificaciones.nota_numerica). Nullable: solo
            // aplica a certificados de capacitación, y ni siquiera todos
            // traen este dato (depende de si la entidad que dictó el curso
            // lo reportó).
            $table->decimal('nota', 4, 2)->nullable()->after('numero_registro');
        });
    }

    public function down(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->dropColumn('nota');
        });
    }
};
