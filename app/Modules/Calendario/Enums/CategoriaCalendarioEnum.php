<?php

declare(strict_types=1);

namespace App\Modules\Calendario\Enums;

/**
 * Con qué tipo de origen se construyó un ítem del calendario unificado
 * (ver CalendarioService::itemsDelMes()): una clase recurrente
 * (Horario/HorarioDia), una evaluación (Evaluacion::fecha) o un evento
 * puntual creado desde este módulo (EventoCalendario).
 */
enum CategoriaCalendarioEnum: string
{
    case CLASE = 'clase';
    case EVALUACION = 'evaluacion';
    case EVENTO = 'evento';

    public function label(): string
    {
        return match ($this) {
            self::CLASE => 'Clase',
            self::EVALUACION => 'Evaluación',
            self::EVENTO => 'Evento',
        };
    }

    public function variantePildora(): string
    {
        return match ($this) {
            self::CLASE => 'neutral',
            self::EVALUACION => 'danger',
            self::EVENTO => 'accent',
        };
    }
}
