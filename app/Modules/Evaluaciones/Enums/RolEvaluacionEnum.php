<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Enums;

/**
 * El papel que juega una Evaluacion dentro de la fórmula de la nota
 * final de un curso (ver EvaluacionService::promedioFinalDelEstudiante()):
 * Promedio Final = 20% Unidad 1 + 20% Unidad 2 + 20% Asistencia + 40%
 * Examen Final, con Sustitutorio reemplazando la nota más baja entre
 * {Unidad 1, Unidad 2, Examen Final} y Aplazado reemplazando
 * directamente al Examen Final.
 */
enum RolEvaluacionEnum: string
{
    case CRITERIO = 'criterio';
    case EXAMEN_FINAL = 'examen_final';
    case SUSTITUTORIO = 'sustitutorio';
    case APLAZADO = 'aplazado';
    case ASISTENCIA = 'asistencia';

    public function label(): string
    {
        return match ($this) {
            self::CRITERIO => 'Criterio',
            self::EXAMEN_FINAL => 'Examen Final',
            self::SUSTITUTORIO => 'Examen Sustitutorio',
            self::APLAZADO => 'Examen Aplazado',
            self::ASISTENCIA => 'Asistencia',
        };
    }

    /**
     * Estos roles son únicos por horario: a diferencia de Criterio (2 a 5
     * por Unidad), solo puede existir una Evaluacion con este rol para un
     * mismo horario.
     */
    public function unicoPorHorario(): bool
    {
        return $this !== self::CRITERIO;
    }
}
