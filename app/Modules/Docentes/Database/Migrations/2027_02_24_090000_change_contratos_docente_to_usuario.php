<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generaliza Contrato de "exclusivo de Docente" a cualquier usuario con
 * cuenta de login (punto 10 del backlog): reemplaza docente_id por
 * user_id. Backfill vía docentes.user_id antes de soltar la columna
 * vieja, para no perder los contratos ya registrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('docente_id')
                ->constrained('users')->cascadeOnDelete();
        });

        // UPDATE...JOIN no es portable entre MySQL y SQLite (este último lo
        // usan los tests) -- se hace fila por fila en vez de un solo
        // statement nativo.
        foreach (DB::table('docentes')->select('id', 'user_id')->get() as $docente) {
            DB::table('contratos')->where('docente_id', $docente->id)->update(['user_id' => $docente->user_id]);
        }

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('docente_id');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->foreignId('docente_id')->nullable()->after('id')
                ->constrained('docentes')->cascadeOnDelete();
        });

        foreach (DB::table('docentes')->select('id', 'user_id')->get() as $docente) {
            DB::table('contratos')->where('user_id', $docente->user_id)->update(['docente_id' => $docente->id]);
        }

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->foreignId('docente_id')->nullable(false)->change();
        });
    }
};
