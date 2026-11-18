<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El instituto tiene varias sedes físicas, cada una con sus propias
 * Aulas -- Sede es el nuevo nivel de agrupación "de arriba", Aula sigue
 * siendo la misma entidad que ya usa Horario::aula_id para el control de
 * choques de horario (no se toca esa parte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('direccion', 255)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        $sedeId = DB::table('sedes')->insertGetId([
            'nombre' => 'Sede Principal',
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('aulas', function (Blueprint $table) {
            $table->foreignId('sede_id')->nullable()->after('id')->constrained('sedes')->nullOnDelete();
        });

        // Las aulas ya existentes quedan bajo la sede por defecto para no
        // dejar ninguna huérfana -- el personal las reorganiza luego desde
        // el panel si hay más de una sede real.
        DB::table('aulas')->update(['sede_id' => $sedeId]);
    }

    public function down(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_id');
        });

        Schema::dropIfExists('sedes');
    }
};
