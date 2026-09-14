<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Número de celular peruano (9 dígitos, empieza en 9). Acepta el prefijo
 * internacional +51 al construirse pero siempre se normaliza sin él.
 */
final readonly class Telefono implements Stringable
{
    private string $numero;

    public function __construct(string $valor)
    {
        $digitos = preg_replace('/[^0-9]/', '', $valor) ?? '';

        if (str_starts_with($digitos, '51') && strlen($digitos) === 11) {
            $digitos = substr($digitos, 2);
        }

        if (! preg_match('/^9[0-9]{8}$/', $digitos)) {
            throw new InvalidArgumentException(
                "El número «{$valor}» no es un celular peruano válido (debe tener 9 dígitos y empezar en 9)."
            );
        }

        $this->numero = $digitos;
    }

    public function conPrefijoInternacional(): string
    {
        return '+51'.$this->numero;
    }

    public function numero(): string
    {
        return $this->numero;
    }

    public function __toString(): string
    {
        return $this->numero;
    }
}
