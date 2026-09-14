<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->unique()->constrained('matriculas')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero_cuotas');
            $table->decimal('monto_total', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_pago');
    }
};
