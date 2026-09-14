<?php

declare(strict_types=1);

namespace App\Modules\Personal\Services;

use App\Modules\Personal\Models\Personal;
use App\Shared\Support\ImportaFilasDeExcel;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

class PersonalService
{
    use ImportaFilasDeExcel;

    public function listar(?string $termino, int $perPage = 15): LengthAwarePaginator
    {
        return Personal::query()
            ->when($termino, fn ($query) => $query->where('nombres', 'like', "%{$termino}%")
                ->orWhere('apellidos', 'like', "%{$termino}%")
                ->orWhere('dni', 'like', "%{$termino}%"))
            ->latest()
            ->paginate($perPage);
    }

    public function dniDisponible(string $dni, ?int $exceptoId = null): bool
    {
        return ! Personal::query()
            ->where('dni', $dni)
            ->when($exceptoId, fn ($query) => $query->where('id', '!=', $exceptoId))
            ->exists();
    }

    /**
     * @param  array{nombres: string, apellidos: string, dni: Dni, celular: ?Telefono, cargo: string, area: ?string, fechaIngreso: ?string}  $datos
     */
    public function registrar(array $datos): Personal
    {
        return Personal::query()->create([
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'dni' => $datos['dni']->valor(),
            'celular' => $datos['celular']?->numero(),
            'cargo' => $datos['cargo'],
            'area' => $datos['area'],
            'fecha_ingreso' => $datos['fechaIngreso'],
            'activo' => true,
        ]);
    }

    /**
     * @param  array{nombres: string, apellidos: string, celular: ?Telefono, cargo: string, area: ?string, fechaIngreso: ?string, activo: bool}  $datos
     */
    public function actualizar(Personal $personal, array $datos): Personal
    {
        $personal->update([
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'celular' => $datos['celular']?->numero(),
            'cargo' => $datos['cargo'],
            'area' => $datos['area'],
            'fecha_ingreso' => $datos['fechaIngreso'],
            'activo' => $datos['activo'],
        ]);

        return $personal->fresh();
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function registrarDesdeFilas(Collection $filas): array
    {
        $exitosos = 0;
        $errores = [];

        foreach ($filas as $indice => $fila) {
            try {
                $nombres = $this->celdaObligatoria($fila, 'nombres');
                $apellidos = $this->celdaObligatoria($fila, 'apellidos');
                $dniTexto = $this->celdaObligatoria($fila, 'dni');
                $cargo = $this->celdaObligatoria($fila, 'cargo');

                if (! $this->dniDisponible($dniTexto)) {
                    throw new InvalidArgumentException("Ya existe una persona registrada con el DNI {$dniTexto}.");
                }

                $celularTexto = $this->celdaOpcional($fila, 'celular');
                $fechaIngresoValor = $fila->get('fecha_ingreso');

                $this->registrar([
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'dni' => new Dni($dniTexto),
                    'celular' => $celularTexto !== null ? new Telefono($celularTexto) : null,
                    'cargo' => $cargo,
                    'area' => $this->celdaOpcional($fila, 'area'),
                    'fechaIngreso' => $fechaIngresoValor !== null && trim((string) $fechaIngresoValor) !== ''
                        ? $this->parsearFecha($fechaIngresoValor)
                        : null,
                ]);

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $indice + 2, 'mensaje' => $this->mensajeDeError($e)];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }
}
