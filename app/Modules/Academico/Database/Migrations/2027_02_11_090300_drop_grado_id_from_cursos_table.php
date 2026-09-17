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
        // Backfill: un curso solo tenía un grado_id -- se traslada a una
        // fila del pivot antes de borrar la columna, para no perder ningún
        // vínculo curso-semestre que ya existiera.
        DB::table('cursos')
            ->whereNotNull('grado_id')
            ->select('id', 'grado_id')
            ->get()
            ->each(function (object $curso): void {
                DB::table('curso_grado')->insert([
                    'curso_id' => $curso->id,
                    'grado_id' => $curso->grado_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('cursos', function (Blueprint $table) {
            $table->dropForeign(['grado_id']);
            $table->dropColumn('grado_id');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->foreignId('grado_id')->nullable()->after('codigo')->constrained('grados')->restrictOnDelete();
        });

        // Un curso pudo haber ganado varios grados mientras la tabla no
        // existía en esta forma; al revertir solo se puede conservar uno
        // por curso (el primero), que es la única forma que soportaba el
        // esquema anterior.
        DB::table('curso_grado')
            ->select('curso_id', 'grado_id')
            ->orderBy('id')
            ->get()
            ->unique('curso_id')
            ->each(function (object $vinculo): void {
                DB::table('cursos')->where('id', $vinculo->curso_id)->update(['grado_id' => $vinculo->grado_id]);
            });
    }
};
