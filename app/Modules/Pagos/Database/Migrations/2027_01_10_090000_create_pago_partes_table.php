<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un pago puede cubrirse con más de un medio a la vez (ej. una parte en
 * efectivo y otra por Yape) sin que eso implique registrar varios pagos
 * separados: cada fila de pago_partes es un monto+método, y su suma debe
 * coincidir con pagos.monto. Se aprueba o rechaza el pago completo (todas
 * sus partes juntas), no cada parte por separado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_partes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->decimal('monto', 8, 2);
            $table->string('metodo', 20);
            $table->timestamps();
        });

        // Todo pago existente hasta ahora tenía un solo monto/método: se
        // migra a una única parte para que ambos modelos queden coherentes
        // desde ya (pagos.metodo/monto se conservan como resumen agregado).
        DB::table('pagos')->orderBy('id')->chunk(200, function ($pagos) {
            foreach ($pagos as $pago) {
                DB::table('pago_partes')->insert([
                    'pago_id' => $pago->id,
                    'monto' => $pago->monto,
                    'metodo' => $pago->metodo,
                    'created_at' => $pago->created_at,
                    'updated_at' => $pago->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_partes');
    }
};
