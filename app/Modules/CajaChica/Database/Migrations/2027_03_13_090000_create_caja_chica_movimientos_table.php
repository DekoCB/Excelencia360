<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja chica: un fondo fijo aparte del Flujo de Caja (cobranza/egresos
 * grandes), para gastos menores del día a día. El saldo no se guarda en
 * una columna -- se calcula sumando/restando estos movimientos según su
 * tipo (ver CajaChicaService::saldoActual()), igual que Flujo de Caja
 * calcula el saldo de un turno a partir de sus movimientos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_chica_movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 15);
            $table->string('categoria', 20)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->decimal('monto', 10, 2);
            $table->date('fecha');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_chica_movimientos');
    }
};
