<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza al entero "semana" (ver las migraciones add_semana_to_*): en
 * vez de un número manual sin ningún vínculo real, una Sección es un
 * bloque con nombre propio (estilo Moodle: "Bienvenida", "Fin de curso")
 * y/o una fecha real de sesión que el propio docente elige a mano --
 * nunca se genera sola a partir del horario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_virtual_id')->constrained('aula_virtual_cursos')->cascadeOnDelete();
            $table->string('nombre', 100)->nullable();
            $table->date('fecha')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['curso_virtual_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secciones');
    }
};
