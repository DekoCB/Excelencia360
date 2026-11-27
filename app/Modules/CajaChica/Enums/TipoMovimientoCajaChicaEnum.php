<?php

declare(strict_types=1);

namespace App\Modules\CajaChica\Enums;

enum TipoMovimientoCajaChicaEnum: string
{
    case ASIGNACION = 'asignacion';
    case REPOSICION = 'reposicion';
    case GASTO = 'gasto';

    public function label(): string
    {
        return match ($this) {
            self::ASIGNACION => 'Asignación inicial',
            self::REPOSICION => 'Reposición',
            self::GASTO => 'Gasto',
        };
    }

    /**
     * Si este tipo aumenta (true) o disminuye (false) el saldo del fondo
     * -- usado por CajaChicaService::saldoActual().
     */
    public function sumaAlSaldo(): bool
    {
        return $this !== self::GASTO;
    }
}
