<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Enums;

enum TipoPreguntaEnum: string
{
    case OPCION_MULTIPLE = 'opcion_multiple';
    case OPCION_UNICA = 'opcion_unica';
    case PREGUNTA_ABIERTA = 'pregunta_abierta';

    public function label(): string
    {
        return match ($this) {
            self::OPCION_MULTIPLE => 'Opción múltiple',
            self::OPCION_UNICA => 'Opción única',
            self::PREGUNTA_ABIERTA => 'Pregunta abierta',
        };
    }

    /**
     * Opción múltiple/única necesitan un banco de alternativas para elegir;
     * una pregunta abierta es texto libre, no tiene alternativas.
     */
    public function requiereAlternativas(): bool
    {
        return $this !== self::PREGUNTA_ABIERTA;
    }

    /**
     * Opción múltiple/única se autocalifican comparando lo elegido contra
     * las alternativas marcadas como correctas; una pregunta abierta
     * siempre necesita que el docente le asigne el puntaje a mano.
     */
    public function esAutoCalificable(): bool
    {
        return $this !== self::PREGUNTA_ABIERTA;
    }
}
