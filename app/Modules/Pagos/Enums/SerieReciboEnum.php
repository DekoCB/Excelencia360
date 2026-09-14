<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

/**
 * Un recibo se emite en dos series (como una libreta de recibos físicos
 * con original y copia): la 001 se entrega al apoderado/estudiante, la 002
 * queda como copia de la institución. Ambas comparten el correlativo
 * (Recibo::$numero_recibo) y el membrete institucional del recibo sale de
 * config/institucion.php (ver resources/views/pdf/partials/cuerpo-recibo.blade.php).
 */
enum SerieReciboEnum: string
{
    case ORIGINAL = '001';
    case COPIA = '002';

    public function titulo(): string
    {
        return match ($this) {
            self::ORIGINAL => 'Recibo de pago',
            self::COPIA => 'Recibo',
        };
    }

    public function numeroCompleto(string $correlativo): string
    {
        return "{$this->value}-{$correlativo}";
    }
}
