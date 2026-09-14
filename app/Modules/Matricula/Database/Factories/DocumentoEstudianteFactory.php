<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Database\Factories;

use App\Modules\Matricula\Enums\TipoDocumentoEnum;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentoEstudiante>
 */
class DocumentoEstudianteFactory extends Factory
{
    protected $model = DocumentoEstudiante::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'tipo' => $this->faker->randomElement(TipoDocumentoEnum::cases()),
            'verificado' => false,
        ];
    }
}
