<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

/**
 * Las 3 franjas fijas en las que la institución organiza sus horarios
 * (ver DiaSemanaEnum): nunca un día suelto, salvo el domingo.
 */
enum FranjaHorarioEnum: string
{
    case LUN_MIE = 'lun_mie';
    case MAR_JUE = 'mar_jue';
    case DOMINGO = 'domingo';

    public function label(): string
    {
        return match ($this) {
            self::LUN_MIE => 'Lunes y Miércoles',
            self::MAR_JUE => 'Martes y Jueves',
            self::DOMINGO => 'Domingo',
        };
    }

    /**
     * @return list<DiaSemanaEnum>
     */
    public function dias(): array
    {
        return match ($this) {
            self::LUN_MIE => [DiaSemanaEnum::LUNES, DiaSemanaEnum::MIERCOLES],
            self::MAR_JUE => [DiaSemanaEnum::MARTES, DiaSemanaEnum::JUEVES],
            self::DOMINGO => [DiaSemanaEnum::DOMINGO],
        };
    }

    /**
     * A qué franja corresponde un conjunto de días, o null si no calza
     * exactamente con ninguna de las 3 (por ejemplo, un horario con una
     * combinación de días distinta a las franjas institucionales).
     *
     * @param  iterable<DiaSemanaEnum>  $dias
     */
    public static function desdeDias(iterable $dias): ?self
    {
        $valores = collect($dias)->map(fn (DiaSemanaEnum $dia) => $dia->value)->sort()->values()->all();

        foreach (self::cases() as $franja) {
            $valoresFranja = collect($franja->dias())->map(fn (DiaSemanaEnum $dia) => $dia->value)->sort()->values()->all();

            if ($valores === $valoresFranja) {
                return $franja;
            }
        }

        return null;
    }
}
