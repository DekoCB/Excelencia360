<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

/**
 * Los 3 periodos en que el MINEDU clasifica a cada estudiante en SIAGIE:
 * completamente independiente del Grupo rotativo de CEBA (ver
 * ModalidadCicloEnum) -- un alumno de cualquier Grupo puede tener
 * cualquiera de estos 3 periodos. Solo ANUAL corresponde además a un
 * Ciclo real (ver Siagie::$ciclo): PRIMERO y SEGUNDO son clasificación
 * pura, sin horarios propios.
 */
enum TipoSiagieEnum: string
{
    case PRIMERO = 'primero';
    case SEGUNDO = 'segundo';
    case ANUAL = 'anual';

    public function label(): string
    {
        return match ($this) {
            self::PRIMERO => '1.er periodo',
            self::SEGUNDO => '2.° periodo',
            self::ANUAL => 'Anual',
        };
    }
}
