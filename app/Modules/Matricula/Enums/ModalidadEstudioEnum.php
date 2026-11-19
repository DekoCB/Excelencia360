<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Enums;

/**
 * Modalidad de estudio que el estudiante elige al matricularse -- no
 * afecta cómo está estructurado el Aula Virtual (es igual para los tres),
 * es solo informativa/de reporte.
 */
enum ModalidadEstudioEnum: string
{
    case DISTANCIA = 'distancia';
    case PRESENCIAL = 'presencial';
    case SEMIPRESENCIAL = 'semipresencial';

    public function label(): string
    {
        return match ($this) {
            self::DISTANCIA => 'Distancia',
            self::PRESENCIAL => 'Presencial',
            self::SEMIPRESENCIAL => 'Semipresencial',
        };
    }
}
