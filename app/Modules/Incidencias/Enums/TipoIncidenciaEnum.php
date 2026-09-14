<?php

declare(strict_types=1);

namespace App\Modules\Incidencias\Enums;

enum TipoIncidenciaEnum: string
{
    case CONDUCTA = 'conducta';
    case DISCIPLINA = 'disciplina';
    case TAREA_NO_REALIZADA = 'tarea_no_realizada';
    case EVALUACION_NO_REALIZADA = 'evaluacion_no_realizada';

    public function label(): string
    {
        return match ($this) {
            self::CONDUCTA => 'Conducta',
            self::DISCIPLINA => 'Disciplina',
            self::TAREA_NO_REALIZADA => 'Tarea no realizada',
            self::EVALUACION_NO_REALIZADA => 'Evaluación no realizada',
        };
    }

    public function requiereTarea(): bool
    {
        return $this === self::TAREA_NO_REALIZADA;
    }

    public function requiereEvaluacion(): bool
    {
        return $this === self::EVALUACION_NO_REALIZADA;
    }
}
