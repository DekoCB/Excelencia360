<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha');
            $table->dateTime('hora_apertura');
            $table->decimal('monto_inicial', 8, 2);
            $table->dateTime('hora_cierre')->nullable();
            $table->decimal('monto_final_declarado', 8, 2)->nullable();
            $table->string('estado', 10)->default('abierto');
            $table->timestamps();

            $table->index(['usuario_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos_caja');
    }
};
