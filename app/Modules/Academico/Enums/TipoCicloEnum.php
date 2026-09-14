<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

/**
 * Las 4 ventanas de admisión rotativas del año (Grupo 1 a 4, cada una
 * ~2 meses después de la anterior): tanto mayores como menores entran por
 * cualquiera de ellas, la que caiga más cerca de su fecha real de
 * matrícula. La duración de estudio de cada estudiante ya no depende del
 * ciclo (antes 2 ciclos de mayores vs. 3 duraciones distintas de
 * menores) sino que se calcula por estudiante desde su propia fecha de
 * matrícula -- ver Matricula::fecha_fin_estudio.
 */
enum TipoCicloEnum: string
{
    case GRUPO_1 = 'grupo_1';
    case GRUPO_2 = 'grupo_2';
    case GRUPO_3 = 'grupo_3';
    case GRUPO_4 = 'grupo_4';

    public function label(): string
    {
        return match ($this) {
            self::GRUPO_1 => 'Grupo 1 (Enero - Junio)',
            self::GRUPO_2 => 'Grupo 2 (Mayo - Octubre)',
            self::GRUPO_3 => 'Grupo 3 (Julio - Diciembre)',
            self::GRUPO_4 => 'Grupo 4 (Noviembre - Abril)',
        };
    }

    public function numero(): int
    {
        return match ($this) {
            self::GRUPO_1 => 1,
            self::GRUPO_2 => 2,
            self::GRUPO_3 => 3,
            self::GRUPO_4 => 4,
        };
    }

    /**
     * Duración nominal de la ventana administrativa (todas duran 6 meses
     * calendario): solo se usa para validar que fecha_inicio/fecha_fin del
     * Ciclo cuadren entre sí, no la duración de estudio de un estudiante
     * en particular.
     */
    public function duracionEnMeses(): int
    {
        return 6;
    }

    /**
     * El mes calendario en que debe iniciar este grupo (los 4 tienen uno
     * fijo, a diferencia del esquema anterior donde solo los ciclos de
     * mayores lo tenían).
     */
    public function mesInicioFijo(): int
    {
        return match ($this) {
            self::GRUPO_1 => 1,
            self::GRUPO_2 => 5,
            self::GRUPO_3 => 7,
            self::GRUPO_4 => 11,
        };
    }

    /**
     * A qué grupo pasa un estudiante de este grupo al culminar su grado:
     * avanza 2 posiciones dentro de las 4 ventanas rotativas (1→3, 2→4,
     * 3→1, 4→2), reflejando que un grado dura ~6 meses. Ver
     * avanzaAlSiguienteAnio() para saber si ese siguiente grupo cae en el
     * año calendario siguiente.
     */
    public function siguiente(): self
    {
        return match ($this) {
            self::GRUPO_1 => self::GRUPO_3,
            self::GRUPO_2 => self::GRUPO_4,
            self::GRUPO_3 => self::GRUPO_1,
            self::GRUPO_4 => self::GRUPO_2,
        };
    }

    /**
     * True si siguiente() cae en el año calendario siguiente (Grupo 3 y
     * Grupo 4 cruzan a enero/mayo del año que viene; Grupo 1 y Grupo 2
     * siguen dentro del mismo año).
     */
    public function avanzaAlSiguienteAnio(): bool
    {
        return match ($this) {
            self::GRUPO_1, self::GRUPO_2 => false,
            self::GRUPO_3, self::GRUPO_4 => true,
        };
    }
}
