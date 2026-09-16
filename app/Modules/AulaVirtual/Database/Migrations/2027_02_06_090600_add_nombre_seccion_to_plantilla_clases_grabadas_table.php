<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_clases_grabadas', function (Blueprint $table) {
            $table->string('nombre_seccion', 100)->nullable()->after('plantilla_id');
            $table->dropColumn('semana');
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_clases_grabadas', function (Blueprint $table) {
            $table->dropColumn('nombre_seccion');
            $table->unsignedInteger('semana')->nullable();
        });
    }
};
