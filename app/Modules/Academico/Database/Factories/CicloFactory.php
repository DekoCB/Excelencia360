<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Siagie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ciclo>
 */
class CicloFactory extends Factory
{
    protected $model = Ciclo::class;

    /**
     * Un Ciclo modalidad=anual real siempre tiene su Siagie tipo=anual
     * vinculado (ver migración 2027_01_23): sin esto, cualquier test que
     * use anual() quedaría con un Ciclo huérfano que Vacaciones/
     * Evaluaciones (que ahora consultan Ciclo::siagie, no
     * Ciclo::modalidad) no reconocerían como anual.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Ciclo $ciclo): void {
            if ($ciclo->modalidad !== ModalidadCicloEnum::ANUAL || $ciclo->siagie_id !== null) {
                return;
            }

            $siagie = Siagie::query()->firstOrCreate(
                ['tipo' => TipoSiagieEnum::ANUAL, 'anio' => $ciclo->anio],
                ['fecha_inicio' => $ciclo->fecha_inicio, 'fecha_fin' => $ciclo->fecha_fin, 'estado' => $ciclo->estado],
            );

            $ciclo->update(['siagie_id' => $siagie->id]);
        });
    }

    public function definition(): array
    {
        $anio = (int) $this->faker->year();
        $inicio = "{$anio}-01-01";
        $fin = "{$anio}-06-30";

        return [
            'nombre' => "Grupo 1 - {$anio}",
            'tipo' => TipoCicloEnum::GRUPO_1,
            'anio' => $anio,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'estado' => EstadoCicloEnum::PLANIFICADO,
        ];
    }

    public function grupo3(): static
    {
        return $this->state(function (array $attributes) {
            $anio = $attributes['anio'];

            return [
                'nombre' => "Grupo 3 - {$anio}",
                'tipo' => TipoCicloEnum::GRUPO_3,
                'fecha_inicio' => "{$anio}-07-01",
                'fecha_fin' => "{$anio}-12-31",
            ];
        });
    }

    public function activo(): static
    {
        return $this->state(['estado' => EstadoCicloEnum::ACTIVO]);
    }

    public function anual(): static
    {
        return $this->state(function (array $attributes) {
            $anio = $attributes['anio'];

            return [
                'nombre' => "SIAGIE Anual - {$anio}",
                'modalidad' => ModalidadCicloEnum::ANUAL,
                'tipo' => null,
                'fecha_inicio' => "{$anio}-03-01",
                'fecha_fin' => "{$anio}-10-31",
            ];
        });
    }
}
