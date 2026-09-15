<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal de Apoderados: un apoderado (misma persona, identificada por DNI)
 * puede tener una fila en `apoderados` por cada hijo matriculado -- la
 * tabla no tiene un unique(dni) que las agrupe. `user_id` no reemplaza esa
 * relación: varias filas de distintos hijos pueden compartir el mismo
 * user_id (una sola cuenta institucional para esa persona), y es el join
 * que usa el portal para saber "qué estudiantes puede ver esta cuenta"
 * (ver MatriculaService::registrarApoderado()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apoderados', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('estudiante_id')->constrained('users')->nullOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('apoderados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
