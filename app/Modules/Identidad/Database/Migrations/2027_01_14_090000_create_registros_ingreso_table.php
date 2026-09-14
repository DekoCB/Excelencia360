<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_ingreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // El ID de sesión de Laravel (tabla `sessions`) que generó este
            // ingreso -- permite cerrar el registro cuando esa sesión se
            // revoca o se cierra explícitamente, sin depender de que el
            // usuario use el botón de "Cerrar sesión".
            $table->string('session_id')->nullable()->index();
            $table->string('nombre');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('iniciado_en');
            $table->timestamp('finalizado_en')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'iniciado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_ingreso');
    }
};
