<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('cargo_adicional_id')->nullable()->after('cuota_id')->constrained('cargos_adicionales')->nullOnDelete();
            $table->index(['cargo_adicional_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex(['cargo_adicional_id', 'estado']);
            $table->dropConstrainedForeignId('cargo_adicional_id');
        });
    }
};
