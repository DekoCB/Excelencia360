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
            $table->dateTime('entregado_en')->nullable()->after('observaciones');
            $table->foreignId('entregado_por')->nullable()->after('entregado_en')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entregado_por');
            $table->dropColumn('entregado_en');
        });
    }
};
