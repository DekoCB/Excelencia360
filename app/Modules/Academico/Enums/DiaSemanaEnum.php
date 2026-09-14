<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

/**
 * Día real de la semana en que se dicta una clase. Cada Horario puede
 * tener varios (ver HorarioDia), uno por cada día en que se reúne, cada
 * uno con su propio hora_inicio/hora_fin.
 */
enum DiaSemanaEnum: string
{
    case LUNES = 'lunes';
    case MARTES = 'martes';
    case MIERCOLES = 'miercoles';
    case JUEVES = 'jueves';
    case VIERNES = 'viernes';
    case SABADO = 'sabado';
    case DOMINGO = 'domingo';

    public function label(): string
    {
        return match ($this) {
            self::LUNES => 'Lunes',
            self::MARTES => 'Martes',
            self::MIERCOLES => 'Miércoles',
            self::JUEVES => 'Jueves',
            self::VIERNES => 'Viernes',
            self::SABADO => 'Sábado',
            self::DOMINGO => 'Domingo',
        };
    }

    /**
     * Equivalente a Carbon::dayOfWeek (0 = domingo … 6 = sábado).
     */
    public function numeroCarbon(): int
    {
        return match ($this) {
            self::DOMINGO => 0,
            self::LUNES => 1,
            self::MARTES => 2,
            self::MIERCOLES => 3,
            self::JUEVES => 4,
            self::VIERNES => 5,
            self::SABADO => 6,
        };
    }

    public static function deCarbon(int $dayOfWeek): self
    {
        return match ($dayOfWeek) {
            0 => self::DOMINGO,
            1 => self::LUNES,
            2 => self::MARTES,
            3 => self::MIERCOLES,
            4 => self::JUEVES,
            5 => self::VIERNES,
            6 => self::SABADO,
            default => throw new \ValueError("Día de semana inválido: {$dayOfWeek}"),
        };
    }

    /**
     * Orden natural para mostrar en listas y selectores: empieza el lunes,
     * más intuitivo para horarios escolares que empezar el domingo.
     *
     * @return list<self>
     */
    public static function ordenSemana(): array
    {
        return [self::LUNES, self::MARTES, self::MIERCOLES, self::JUEVES, self::VIERNES, self::SABADO, self::DOMINGO];
    }
}
