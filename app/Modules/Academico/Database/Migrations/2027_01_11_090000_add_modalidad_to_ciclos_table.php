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
            $table->string('modalidad', 15)->default('seis_meses')->after('tipo');
        });

        Schema::table('ciclos', function (Blueprint $table) {
            $table->string('tipo', 15)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ciclos', function (Blueprint $table) {
            $table->string('tipo', 15)->nullable(false)->change();
        });

        Schema::table('ciclos', function (Blueprint $table) {
            $table->dropColumn('modalidad');
        });
    }
};
