<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    protected $model = Curso::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement([
                'Matemática',
                'Ciencia Tecnología y Salud',
                'Comunicación',
                'Desarrollo personal y ciudadano',
                'Inglés',
                'Religión',
                'Educación para el trabajo',
                'Educación física',
            ]),
            // codigo es único en la tabla. Antes usaba
            // faker->unique()->bothify('CUR-###') (solo 1000 combinaciones
            // posibles) -- mismo riesgo de agotar el rango que tenían
            // GradoFactory::orden y AulaFactory::nombre, ver el comentario
            // en GradoFactory.
            'codigo' => 'CUR-'.str_pad((string) (Curso::count() + 1), 3, '0', STR_PAD_LEFT),
            'grado_id' => Grado::factory(),
            'horas' => $this->faker->numberBetween(60, 120),
            'activo' => true,
        ];
    }
}
