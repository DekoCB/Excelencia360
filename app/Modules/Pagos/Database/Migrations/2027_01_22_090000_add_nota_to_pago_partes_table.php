<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle libre y opcional sobre el método de una parte del pago -- por
 * ejemplo, de quién es la cuenta que recibió un Yape ("Walter", "Director"),
 * cuando no fue la cuenta institucional. No reemplaza el método (que sigue
 * siendo el enum cerrado, del que dependen los reportes), es un dato aparte
 * que se muestra junto a él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_partes', function (Blueprint $table) {
            $table->string('nota', 40)->nullable()->after('metodo');
        });
    }

    public function down(): void
    {
        Schema::table('pago_partes', function (Blueprint $table) {
            $table->dropColumn('nota');
        });
    }
};
