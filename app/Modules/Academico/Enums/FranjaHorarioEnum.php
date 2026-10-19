<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

/**
 * Las 2 franjas fijas en las que la institución organiza sus horarios
 * (ver DiaSemanaEnum): entre semana (lunes a viernes) o fin de semana
 * (sábados y domingos), nunca un día suelto.
 */
enum FranjaHorarioEnum: string
{
    case LUNES_VIERNES = 'lunes_viernes';
    case SABADOS_DOMINGOS = 'sabados_domingos';

    public function label(): string
    {
        return match ($this) {
            self::LUNES_VIERNES => 'Lunes a Viernes',
            self::SABADOS_DOMINGOS => 'Sábados y Domingos',
        };
    }

    /**
     * @return list<DiaSemanaEnum>
     */
    public function dias(): array
    {
        return match ($this) {
            self::LUNES_VIERNES => [DiaSemanaEnum::LUNES, DiaSemanaEnum::MARTES, DiaSemanaEnum::MIERCOLES, DiaSemanaEnum::JUEVES, DiaSemanaEnum::VIERNES],
            self::SABADOS_DOMINGOS => [DiaSemanaEnum::SABADO, DiaSemanaEnum::DOMINGO],
        };
    }

    /**
     * A qué franja corresponde un conjunto de días, o null si no calza
     * exactamente con ninguna de las 2 (por ejemplo, un horario con una
     * combinación de días distinta a las franjas institucionales -- entre
     * ellos, cualquier horario creado con las franjas institucionales
     * anteriores a este cambio).
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
