<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            // Nullable: las aulas ya existentes (creadas antes de este
            // cambio) quedan como aulas "sueltas", sin grupo ni letra
            // asignados, y siguen sirviendo para horarios sin romperse.
            $table->foreignId('ciclo_id')->nullable()->after('id')->constrained('ciclos')->nullOnDelete();
            $table->string('letra', 1)->nullable()->after('ciclo_id');
        });
    }

    public function down(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ciclo_id');
            $table->dropColumn('letra');
        });
    }
};
