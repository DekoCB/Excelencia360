<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

/**
 * La vía de estudio de un Ciclo: "seis_meses" es el esquema rotativo ya
 * existente (Grupo 1 a 4, ver TipoCicloEnum) -- no tiene nada que ver con
 * SIAGIE, son dos clasificaciones independientes -- y "anual" es SIAGIE
 * anual: un ciclo independiente que no rota entre Grupos, corre el año
 * escolar completo (8 meses de clases + 2 de vacaciones) y no tiene
 * TipoCicloEnum asociado (Ciclo::tipo queda null para estos).
 */
enum ModalidadCicloEnum: string
{
    case SEIS_MESES = 'seis_meses';
    case ANUAL = 'anual';

    public function label(): string
    {
        return match ($this) {
            self::SEIS_MESES => 'Grupo rotativo (6 meses)',
            self::ANUAL => 'SIAGIE anual',
        };
    }

    /**
     * Cuántos exámenes mensuales entran en el promedio final de un curso
     * (los últimos N por fecha, ver EvaluacionService::promedioDelEstudiante()):
     * 6 para un Grupo de 6 meses, 8 para SIAGIE anual (8 meses de clases).
     */
    public function examenesQueCuentan(): int
    {
        return match ($this) {
            self::SEIS_MESES => 6,
            self::ANUAL => 8,
        };
    }
}
