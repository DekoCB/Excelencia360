<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Seeders;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\ProgramaEstudio;
use Illuminate\Database\Seeder;

class AcademicoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $programaEstudio = ProgramaEstudio::query()->create([
            'nombre' => 'Programa Académico Regular',
            'activo' => true,
        ]);

        $grados = collect([
            ['nombre' => 'Semestre 1', 'orden' => 1],
            ['nombre' => 'Semestre 2', 'orden' => 2],
            ['nombre' => 'Semestre 3', 'orden' => 3],
            ['nombre' => 'Semestre 4', 'orden' => 4],
        ])->map(fn (array $datos) => Grado::query()->create([...$datos, 'programa_estudio_id' => $programaEstudio->id]));

        $cursoComunicacion = Curso::query()->create([
            'nombre' => 'Comunicación',
            'codigo' => 'COM-101',
            'grado_id' => $grados[0]->id,
            'horas' => 80,
        ]);

        Curso::query()->create([
            'nombre' => 'Matemática',
            'codigo' => 'MAT-101',
            'grado_id' => $grados[0]->id,
            'horas' => 80,
        ]);

        // 3 periodos académicos por año (convención "{año}-0/1/2", fechas
        // libres): el activo para la demo es el que contiene "hoy", para
        // que haya datos con los que probar el resto de módulos desde ya.
        $hoy = now();
        $anio = (int) $hoy->format('Y');

        $periodos = collect([
            ['nombre' => "{$anio}-0", 'inicio' => now()->startOfYear(), 'fin' => now()->startOfYear()->addMonths(4)->subDay()],
            ['nombre' => "{$anio}-1", 'inicio' => now()->startOfYear()->addMonths(4), 'fin' => now()->startOfYear()->addMonths(8)->subDay()],
            ['nombre' => "{$anio}-2", 'inicio' => now()->startOfYear()->addMonths(8), 'fin' => now()->endOfYear()],
        ]);

        $nombrePeriodoActivo = $periodos
            ->filter(fn (array $datos) => $datos['inicio']->lessThanOrEqualTo($hoy) && $datos['fin']->greaterThanOrEqualTo($hoy))
            ->first()['nombre'] ?? $periodos->first()['nombre'];

        $ciclosCreados = $periodos->map(fn (array $datos) => [
            'nombre' => $datos['nombre'],
            'ciclo' => Ciclo::query()->create([
                'nombre' => $datos['nombre'],
                'anio' => $anio,
                'fecha_inicio' => $datos['inicio']->toDateString(),
                'fecha_fin' => $datos['fin']->toDateString(),
                'estado' => $datos['nombre'] === $nombrePeriodoActivo ? EstadoCicloEnum::ACTIVO : EstadoCicloEnum::PLANIFICADO,
            ]),
        ])->keyBy('nombre');

        $ciclo = $ciclosCreados[$nombrePeriodoActivo]['ciclo'];

        $aulaA = Aula::query()->create([
            'ciclo_id' => $ciclo->id,
            'letra' => 'A',
            'nombre' => "Aula A. {$ciclo->nombre}",
            'capacidad' => 30,
            'ubicacion' => 'Piso 1',
        ]);

        $docente = User::query()->where('email', 'docente@ceba.test')->first();

        if ($docente) {
            // El Grado 1 (orden 1) siempre corresponde al Aula A dentro de
            // su periodo/Ciclo -- ver Grado::letraAula().
            $horario = Horario::query()->create([
                'curso_id' => $cursoComunicacion->id,
                'docente_id' => $docente->id,
                'aula_id' => $aulaA->id,
                'ciclo_id' => $ciclo->id,
                'grado_id' => $grados[0]->id,
            ]);

            $horario->dias()->createMany([
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::MIERCOLES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ]);
        }
    }
}
