<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Enums;

/**
 * Escala cualitativa 0-20 usada en educación básica en Perú.
 */
enum NotaLetraEnum: string
{
    case AD = 'AD';
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public static function desde(float $notaNumerica): self
    {
        return match (true) {
            $notaNumerica >= 18 => self::AD,
            $notaNumerica >= 14 => self::A,
            $notaNumerica >= 11 => self::B,
            default => self::C,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AD => 'Logro destacado',
            self::A => 'Logro esperado',
            self::B => 'En proceso',
            self::C => 'En inicio',
        };
    }
}
