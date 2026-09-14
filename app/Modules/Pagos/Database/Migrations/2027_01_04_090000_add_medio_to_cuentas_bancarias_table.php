<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropColumn(['banco', 'numero_cuenta', 'cci']);
        });

        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->string('medio', 20)->default('banco')->after('id');
            $table->string('banco', 50)->nullable()->after('medio');
            $table->string('numero_cuenta', 30)->nullable()->after('banco');
            $table->string('cci', 30)->nullable()->after('numero_cuenta');
            $table->string('tipo_billetera', 20)->nullable()->after('cci');
            $table->string('celular', 20)->nullable()->after('tipo_billetera');
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->dropColumn(['medio', 'tipo_billetera', 'celular']);
        });

        Schema::table('cuentas_bancarias', function (Blueprint $table) {
            $table->string('banco', 50)->nullable(false)->after('id');
            $table->string('numero_cuenta', 30)->nullable(false)->after('banco');
            $table->string('cci', 30)->nullable()->after('numero_cuenta');
        });
    }
};
