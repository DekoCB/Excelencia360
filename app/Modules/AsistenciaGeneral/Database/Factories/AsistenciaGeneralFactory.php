<?php

declare(strict_types=1);

namespace App\Modules\AsistenciaGeneral\Database\Factories;

use App\Modules\AsistenciaGeneral\Models\AsistenciaGeneral;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\TurnoDiaEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AsistenciaGeneral>
 */
class AsistenciaGeneralFactory extends Factory
{
    protected $model = AsistenciaGeneral::class;

    public function definition(): array
    {
        return [
            'persona_type' => Estudiante::class,
            'persona_id' => Estudiante::factory(),
            'fecha' => now()->format('Y-m-d'),
            'turno' => TurnoDiaEnum::MANANA,
            'hora_ingreso' => now(),
        ];
    }

    public function paraPersona(object $persona): self
    {
        return $this->state([
            'persona_type' => $persona::class,
            'persona_id' => $persona->getKey(),
        ]);
    }

    public function conSalida(): self
    {
        return $this->state(fn (array $atributos) => [
            'hora_salida' => Carbon::parse($atributos['hora_ingreso'] ?? now())->addHours(4),
        ]);
    }
}
