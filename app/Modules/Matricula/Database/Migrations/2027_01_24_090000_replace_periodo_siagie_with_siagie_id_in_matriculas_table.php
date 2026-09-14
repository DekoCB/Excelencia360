<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * periodo_siagie era una etiqueta libre (string) sin fechas ni
     * administración propia; se reemplaza por una relación real a Siagie
     * (ver 2027_01_21_090000_create_siagies_table). Cada valor ya guardado
     * se migra a su fila Siagie correspondiente (creándola si no existe
     * todavía), usando el año del Ciclo de esa matrícula.
     */
    public function up(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->foreignId('siagie_id')->nullable()->after('grado_id')->constrained('siagies')->nullOnDelete();
        });

        $matriculas = DB::table('matriculas')
            ->join('ciclos', 'ciclos.id', '=', 'matriculas.ciclo_id')
            ->whereNotNull('matriculas.periodo_siagie')
            ->select('matriculas.id as matricula_id', 'matriculas.periodo_siagie', 'ciclos.anio')
            ->get();

        foreach ($matriculas as $matricula) {
            $tipo = $this->mapearTipo($matricula->periodo_siagie);

            $siagieId = DB::table('siagies')
                ->where('tipo', $tipo)
                ->where('anio', $matricula->anio)
                ->value('id');

            if ($siagieId === null) {
                $siagieId = DB::table('siagies')->insertGetId([
                    'tipo' => $tipo,
                    'anio' => $matricula->anio,
                    'estado' => 'activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('matriculas')->where('id', $matricula->matricula_id)->update(['siagie_id' => $siagieId]);
        }

        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropColumn('periodo_siagie');
        });
    }

    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->string('periodo_siagie', 10)->nullable()->after('grado_id');
        });

        $matriculas = DB::table('matriculas')
            ->join('siagies', 'siagies.id', '=', 'matriculas.siagie_id')
            ->select('matriculas.id as matricula_id', 'siagies.tipo')
            ->get();

        foreach ($matriculas as $matricula) {
            DB::table('matriculas')->where('id', $matricula->matricula_id)->update(['periodo_siagie' => $this->mapearTipoInverso($matricula->tipo)]);
        }

        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('siagie_id');
        });
    }

    /**
     * El PeriodoSiagieEnum original usaba '1'/'2'/'anual'; TipoSiagieEnum
     * (que reemplaza esa columna) usa 'primero'/'segundo'/'anual'.
     */
    private function mapearTipo(string $periodoAntiguo): string
    {
        return match ($periodoAntiguo) {
            '1' => 'primero',
            '2' => 'segundo',
            default => $periodoAntiguo,
        };
    }

    private function mapearTipoInverso(string $tipoNuevo): string
    {
        return match ($tipoNuevo) {
            'primero' => '1',
            'segundo' => '2',
            default => $tipoNuevo,
        };
    }
};
