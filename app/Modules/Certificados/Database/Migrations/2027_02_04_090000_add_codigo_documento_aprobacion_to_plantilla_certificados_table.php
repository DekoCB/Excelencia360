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
            $table->string('codigo_documento_aprobacion', 150)->nullable()->after('pie_nota');
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_certificados', function (Blueprint $table) {
            $table->dropColumn('codigo_documento_aprobacion');
        });
    }
};
