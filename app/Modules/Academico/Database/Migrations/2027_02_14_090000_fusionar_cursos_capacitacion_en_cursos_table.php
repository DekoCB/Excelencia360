<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fusión completa del catálogo "cursos_capacitacion" dentro de "cursos"
 * (decisión del cliente, punto 3 del backlog del 2026-09-30): un curso de
 * capacitación pasa a ser una fila más de `cursos` con tipo=capacitacion,
 * sin semestre ni horario. Producción ya tiene certificados reales
 * emitidos contra `cursos_capacitacion` (lotes GE-2026-004/012), así que
 * esta migración remapea cada certificado a su curso nuevo antes de borrar
 * la tabla vieja -- no se pierde el vínculo de ningún certificado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->string('tipo', 20)->default('academico')->after('codigo');
            $table->string('documento_autorizacion', 150)->nullable()->after('horas');
        });

        Schema::table('certificados', function (Blueprint $table) {
            $table->foreignId('curso_id')->nullable()->after('curso_capacitacion_id')
                ->constrained('cursos')->nullOnDelete();
        });

        DB::transaction(function () {
            $mapaIds = [];

            foreach (DB::table('cursos_capacitacion')->get() as $cursoCapacitacion) {
                $nuevoId = DB::table('cursos')->insertGetId([
                    'nombre' => $cursoCapacitacion->nombre,
                    'codigo' => $this->generarCodigoCapacitacion($cursoCapacitacion->nombre),
                    'tipo' => 'capacitacion',
                    'horas' => $cursoCapacitacion->horas_lectivas,
                    'documento_autorizacion' => $cursoCapacitacion->documento_autorizacion,
                    'activo' => true,
                    'created_at' => $cursoCapacitacion->created_at,
                    'updated_at' => $cursoCapacitacion->updated_at,
                ]);

                $mapaIds[$cursoCapacitacion->id] = $nuevoId;
            }

            foreach ($mapaIds as $idViejo => $idNuevo) {
                DB::table('certificados')->where('curso_capacitacion_id', $idViejo)->update(['curso_id' => $idNuevo]);
            }
        });

        Schema::table('certificados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curso_capacitacion_id');
        });

        Schema::dropIfExists('cursos_capacitacion');
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'documento_autorizacion']);
        });

        Schema::create('cursos_capacitacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->unsignedSmallInteger('horas_lectivas');
            $table->string('documento_autorizacion', 150)->nullable();
            $table->timestamps();
        });

        Schema::table('certificados', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curso_id');
            $table->foreignId('curso_capacitacion_id')->nullable()->after('matricula_id')
                ->constrained('cursos_capacitacion')->nullOnDelete();
        });
    }

    /**
     * Mismo criterio que CursoService::generarCodigo(), pero con un prefijo
     * fijo en vez de depender de un Grado (que un curso de capacitación no
     * tiene): CAP- + iniciales del nombre, con sufijo numérico si choca.
     */
    private function generarCodigoCapacitacion(string $nombre): string
    {
        $base = 'CAP-'.$this->iniciales($nombre);

        if (! DB::table('cursos')->where('codigo', $base)->exists()) {
            return $base;
        }

        $sufijo = 2;

        while (DB::table('cursos')->where('codigo', $codigo = "{$base}-{$sufijo}")->exists()) {
            $sufijo++;
        }

        return $codigo;
    }

    private function iniciales(string $nombre): string
    {
        $sinTildes = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'],
            ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'],
            $nombre,
        );

        $soloLetras = preg_replace('/[^A-Za-z]/', '', $sinTildes) ?? '';

        return mb_strtoupper(mb_substr($soloLetras, 0, 3));
    }
};
