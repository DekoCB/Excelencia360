<?php

declare(strict_types=1);

namespace App\Modules\Matricula\DTOs;

final readonly class RegistrarMatriculaData
{
    /**
     * @param  ?list<int>  $cursoIds  Cursos específicos del semestre a los que
     *                                queda matriculado -- si se deja null/vacío, queda en todos los cursos
     *                                del semestre (comportamiento por defecto, ver Matricula::scopeDelHorario()).
     */
    public function __construct(
        public int $cicloId,
        public int $gradoId,
        public ?string $observaciones,
        public ?int $registradoPor,
        public ?string $fechaMatricula = null,
        public ?array $cursoIds = null,
    ) {}
}
