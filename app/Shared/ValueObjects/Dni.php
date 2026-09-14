<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Documento de identidad (DNI peruano de 8 dígitos, o carné de extranjería
 * alfanumérico de hasta 12 caracteres). Se valida una única vez al construirse.
 */
final readonly class Dni implements Stringable
{
    private string $valor;

    public function __construct(string $valor)
    {
        $normalizado = strtoupper(trim($valor));

        if ($normalizado === '') {
            throw new InvalidArgumentException('El documento de identidad no puede estar vacío.');
        }

        if (! preg_match('/^[0-9A-Z]{8,12}$/', $normalizado)) {
            throw new InvalidArgumentException(
                "El documento de identidad «{$valor}» no es válido: debe tener entre 8 y 12 caracteres alfanuméricos."
            );
        }

        $this->valor = $normalizado;
    }

    public function esDniPeruano(): bool
    {
        return preg_match('/^[0-9]{8}$/', $this->valor) === 1;
    }

    public function equals(self $otro): bool
    {
        return $this->valor === $otro->valor;
    }

    public function valor(): string
    {
        return $this->valor;
    }

    /**
     * Correo institucional autoasignado: siempre {dni}@ceba.test, en vez de
     * requerir que se ingrese uno manualmente al crear un usuario o
     * matricular un estudiante.
     */
    public function correoInstitucional(): string
    {
        return strtolower($this->valor).'@ceba.test';
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
