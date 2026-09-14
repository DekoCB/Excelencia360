<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_pago_id')->constrained('planes_pago')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->decimal('monto', 8, 2);
            $table->date('fecha_vencimiento');
            $table->string('estado', 15)->default('pendiente');
            $table->timestamps();

            $table->unique(['plan_pago_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
