<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materiales', function (Blueprint $table) {
            // Nula = aparece en el apartado "Bienvenida", antes de la
            // Semana 1. No es obligatoria: el docente puede dejar
            // contenido sin clasificar.
            $table->unsignedSmallInteger('semana')->nullable()->after('curso_virtual_id');
        });
    }

    public function down(): void
    {
        Schema::table('materiales', function (Blueprint $table) {
            $table->dropColumn('semana');
        });
    }
};
