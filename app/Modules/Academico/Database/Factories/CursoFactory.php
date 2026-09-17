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

    /**
     * grado_id ya no es una columna de cursos (pasó a la tabla pivote
     * curso_grado): se vincula un Grado por defecto acá, después de crear
     * el curso, para que Curso::factory()->create() sin más parámetros siga
     * dejando el curso con un grado (mismo comportamiento visible que
     * antes). Un test que necesite grados específicos usa
     * ->hasAttached($grado) o llama a $curso->grados()->sync(...) él mismo.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Curso $curso): void {
            if ($curso->grados()->exists()) {
                return;
            }

            $curso->grados()->attach(Grado::factory()->create());
        });
    }

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
            'horas' => $this->faker->numberBetween(60, 120),
            'activo' => true,
        ];
    }
}
