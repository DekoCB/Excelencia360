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
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->foreignId('curso_virtual_id')->nullable()->after('horario_id')
                ->constrained('aula_virtual_cursos')->cascadeOnDelete();
            $table->foreignId('seccion_id')->nullable()->after('curso_virtual_id')
                ->constrained('secciones')->nullOnDelete();
            $table->string('tipo', 20)->default('fisico')->after('nombre');
        });

        $this->vincularCursoVirtual();
    }

    /**
     * Toda evaluación existente queda ligada al aula virtual de su horario
     * -- ya existe siempre, porque HorarioService::crear() la activa
     * automáticamente desde antes de esta migración. Por las dudas (datos
     * más viejos que ese cambio), se crea la que falte en vez de asumir.
     */
    private function vincularCursoVirtual(): void
    {
        $evaluaciones = DB::table('evaluaciones')->whereNull('curso_virtual_id')->get(['id', 'horario_id']);

        foreach ($evaluaciones as $evaluacion) {
            $cursoVirtualId = DB::table('aula_virtual_cursos')->where('horario_id', $evaluacion->horario_id)->value('id');

            if (! $cursoVirtualId) {
                $cursoVirtualId = DB::table('aula_virtual_cursos')->insertGetId([
                    'horario_id' => $evaluacion->horario_id,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('evaluaciones')->where('id', $evaluacion->id)->update(['curso_virtual_id' => $cursoVirtualId]);
        }
    }

    public function down(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curso_virtual_id');
            $table->dropConstrainedForeignId('seccion_id');
            $table->dropColumn('tipo');
        });
    }
};
