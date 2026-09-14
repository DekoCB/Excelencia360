<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

/**
 * CEBA ofrece planes de pago desde el pago único hasta 10 cuotas.
 */
enum NumeroCuotasEnum: int
{
    case UNA = 1;
    case DOS = 2;
    case TRES = 3;
    case CUATRO = 4;
    case CINCO = 5;
    case SEIS = 6;
    case SIETE = 7;
    case OCHO = 8;
    case NUEVE = 9;
    case DIEZ = 10;

    public function label(): string
    {
        return $this === self::UNA ? 'Pago único' : "{$this->value} cuotas";
    }
}
