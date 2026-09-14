<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Seeders;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AcademicoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $grados = collect([
            ['nombre' => 'Grado 1', 'orden' => 1],
            ['nombre' => 'Grado 2', 'orden' => 2],
            ['nombre' => 'Grado 3', 'orden' => 3],
            ['nombre' => 'Grado 4', 'orden' => 4],
        ])->map(fn (array $datos) => Grado::query()->create($datos));

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

        // Los 4 grupos rotativos del año, cada uno con su propia Aula A
        // (grados 1-2) y Aula B (grados 3-4). El "activo" para la demo es
        // el que ya empezó más recientemente sin pasarse de hoy, para que
        // haya datos con los que probar el resto de módulos desde ya.
        $hoy = now();
        $anio = (int) $hoy->format('Y');

        $ventanas = collect(TipoCicloEnum::cases())->mapWithKeys(function (TipoCicloEnum $tipo) use ($anio) {
            $inicio = Carbon::create($anio, $tipo->mesInicioFijo(), 1);
            $fin = $inicio->copy()->addMonths($tipo->duracionEnMeses())->subDay();

            return [$tipo->value => ['tipo' => $tipo, 'inicio' => $inicio, 'fin' => $fin]];
        });

        $tipoActivo = $ventanas
            ->filter(fn (array $datos) => $datos['inicio']->lessThanOrEqualTo($hoy))
            ->sortByDesc(fn (array $datos) => $datos['inicio'])
            ->first()['tipo'] ?? TipoCicloEnum::GRUPO_1;

        $grupos = $ventanas->map(function (array $datos) use ($tipoActivo) {
            $ciclo = Ciclo::query()->create([
                'nombre' => $datos['tipo']->label(),
                'tipo' => $datos['tipo'],
                'anio' => $datos['inicio']->year,
                'fecha_inicio' => $datos['inicio']->toDateString(),
                'fecha_fin' => $datos['fin']->toDateString(),
                'estado' => $datos['tipo'] === $tipoActivo ? EstadoCicloEnum::ACTIVO : EstadoCicloEnum::PLANIFICADO,
            ]);

            $aulaA = Aula::query()->create([
                'ciclo_id' => $ciclo->id,
                'letra' => 'A',
                'nombre' => "Aula A. {$ciclo->nombre}",
                'capacidad' => 30,
                'ubicacion' => 'Piso 1',
            ]);

            $aulaB = Aula::query()->create([
                'ciclo_id' => $ciclo->id,
                'letra' => 'B',
                'nombre' => "Aula B. {$ciclo->nombre}",
                'capacidad' => 25,
                'ubicacion' => 'Piso 1',
            ]);

            return ['ciclo' => $ciclo, 'aulaA' => $aulaA, 'aulaB' => $aulaB];
        });

        $ciclo = $grupos[$tipoActivo->value]['ciclo'];
        $aulaA = $grupos[$tipoActivo->value]['aulaA'];

        // Centrado en "hoy" (no en el inicio del ciclo) para que el periodo
        // quede abierto sin importar en qué punto del ciclo se corra el seeder.
        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(20),
        ]);

        $docente = User::query()->where('email', 'docente@ceba.test')->first();

        if ($docente) {
            // El Grado 1 (orden 1) siempre corresponde al Aula A dentro de
            // su Grupo/Ciclo -- ver Grado::letraAula().
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
