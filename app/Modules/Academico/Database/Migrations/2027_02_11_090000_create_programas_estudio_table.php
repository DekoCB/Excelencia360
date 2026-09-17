<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programas_estudio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Contenedor provisional para los semestres/cursos que ya existen:
        // el usuario lo renombra o lo divide en las carreras reales desde
        // el panel, sin inventar nombres institucionales acá.
        DB::table('programas_estudio')->insert([
            'nombre' => 'Programa de Estudio General',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('programas_estudio');
    }
};
