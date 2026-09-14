<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_certificados', function (Blueprint $table) {
            $table->string('tipo', 30)->default('certificado_estudios')->after('id');
        });

        Schema::table('plantilla_certificados', function (Blueprint $table) {
            $table->unique('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_certificados', function (Blueprint $table) {
            $table->dropUnique(['tipo']);
            $table->dropColumn('tipo');
        });
    }
};
