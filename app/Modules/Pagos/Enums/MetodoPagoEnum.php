<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

enum MetodoPagoEnum: string
{
    case EFECTIVO = 'efectivo';
    case TRANSFERENCIA = 'transferencia';
    case YAPE = 'yape';
    case PLIN = 'plin';
    case OTRO = 'otro';

    // No es un medio elegible para una parte del pago: es el valor que
    // PagoService::registrar() calcula automáticamente para Pago::$metodo
    // cuando el pago se cubrió con más de un método distinto a la vez.
    case MIXTO = 'mixto';

    public function label(): string
    {
        return match ($this) {
            self::EFECTIVO => 'Efectivo',
            self::TRANSFERENCIA => 'Transferencia',
            self::YAPE => 'Yape',
            self::PLIN => 'Plin',
            self::OTRO => 'Otro',
            self::MIXTO => 'Mixto',
        };
    }

    /**
     * Los métodos que una persona puede elegir para una parte de un pago
     * (todos menos Mixto, que no es un método real sino el resumen que
     * calcula el sistema cuando hay más de uno).
     *
     * @return list<self>
     */
    public static function seleccionables(): array
    {
        return array_filter(self::cases(), fn (self $metodo) => $metodo !== self::MIXTO);
    }
}
