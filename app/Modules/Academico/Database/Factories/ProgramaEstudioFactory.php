<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Models\ProgramaEstudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramaEstudio>
 */
class ProgramaEstudioFactory extends Factory
{
    protected $model = ProgramaEstudio::class;

    public function definition(): array
    {
        // Sin faker->unique(): con solo 6 nombres posibles se agotaría
        // rápido dentro de un mismo proceso PHP (ver el comentario de
        // GradoFactory::orden sobre el mismo problema). El sufijo con el
        // conteo actual alcanza para que cada fila sea única.
        $nombre = $this->faker->randomElement([
            'Contabilidad',
            'Administración de Empresas',
            'Computación e Informática',
            'Enfermería Técnica',
            'Electrotecnia Industrial',
            'Secretariado Ejecutivo',
        ]);

        return [
            'nombre' => $nombre.' '.(ProgramaEstudio::count() + 1),
            'activo' => true,
        ];
    }
}
