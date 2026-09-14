<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->unique()->constrained('pagos')->cascadeOnDelete();
            $table->string('numero_recibo', 20)->unique();
            $table->dateTime('emitido_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};
