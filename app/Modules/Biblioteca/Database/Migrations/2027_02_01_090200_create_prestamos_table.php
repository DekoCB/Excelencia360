<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ejemplar_id')->constrained('ejemplares')->restrictOnDelete();
            $table->foreignId('solicitante_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('entregado_por')->constrained('users')->restrictOnDelete();
            $table->date('fecha_prestamo');
            $table->date('fecha_devolucion_esperada');
            $table->date('fecha_devolucion_real')->nullable();
            $table->string('estado', 15)->default('prestado');
            $table->string('observaciones', 255)->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha_devolucion_esperada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};
