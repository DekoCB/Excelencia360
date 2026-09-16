<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clases_grabadas', function (Blueprint $table) {
            $table->foreignId('seccion_id')->nullable()->after('curso_virtual_id')->constrained('secciones')->nullOnDelete();
            $table->dropColumn('semana');
        });
    }

    public function down(): void
    {
        Schema::table('clases_grabadas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seccion_id');
            $table->unsignedSmallInteger('semana')->nullable()->after('curso_virtual_id');
        });
    }
};
