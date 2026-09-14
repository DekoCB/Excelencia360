<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Horario>
 */
class HorarioFactory extends Factory
{
    protected $model = Horario::class;

    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            'docente_id' => User::factory(),
            'aula_id' => Aula::factory(),
            'ciclo_id' => Ciclo::factory(),
            'grado_id' => Grado::factory(),
        ];
    }

    /**
     * Un día por defecto (con horario 18:00–20:00) para que $horario->dias
     * nunca quede vacío en pruebas que no les importa qué día específico
     * es, solo que el horario exista.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Horario $horario) {
            $horario->dias()->create([
                'dia_semana' => $this->faker->randomElement(DiaSemanaEnum::cases()),
                'hora_inicio' => '18:00:00',
                'hora_fin' => '20:00:00',
            ]);
        });
    }
}
