<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grados', function (Blueprint $table) {
            $table->dropUnique(['tipo_publico', 'orden']);
            $table->dropColumn('tipo_publico');
            $table->unique('orden');
        });
    }

    public function down(): void
    {
        Schema::table('grados', function (Blueprint $table) {
            $table->dropUnique(['orden']);
            $table->string('tipo_publico', 10)->after('nombre');
            $table->unique(['tipo_publico', 'orden']);
        });
    }
};
