<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use Throwable;

/**
 * Lectura de celdas compartida por las cargas masivas por Excel (ver
 * MatriculaService::registrarEstudiantesDesdeFilas() y
 * DocenteService::registrarDesdeFilas()): cada fila llega como una
 * Collection indexada por el nombre de columna del encabezado (ver
 * HojaConEncabezadosImport), sin garantía de que las celdas opcionales
 * existan o de que las fechas vengan en un formato consistente.
 */
trait ImportaFilasDeExcel
{
    /**
     * @param  Collection<string, mixed>  $fila
     */
    private function celdaOpcional(Collection $fila, string $clave): ?string
    {
        $valor = $fila->get($clave);

        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    /**
     * @param  Collection<string, mixed>  $fila
     */
    private function celdaObligatoria(Collection $fila, string $clave, ?string $mensaje = null): string
    {
        $valor = $this->celdaOpcional($fila, $clave);

        if ($valor === null) {
            throw new InvalidArgumentException($mensaje ?? "La columna «{$clave}» es obligatoria.");
        }

        return $valor;
    }

    /**
     * @return string Fecha normalizada a 'Y-m-d'.
     */
    private function parsearFecha(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return Carbon::instance($valor)->format('Y-m-d');
        }

        if (is_numeric($valor)) {
            return Carbon::instance(FechaExcel::excelToDateTimeObject((float) $valor))->format('Y-m-d');
        }

        $texto = trim((string) $valor);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, $texto)->format('Y-m-d');
            } catch (Throwable) {
                continue;
            }
        }

        throw new InvalidArgumentException("La fecha «{$texto}» no tiene un formato reconocible (usa dd/mm/aaaa).");
    }

    private function mensajeDeError(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return $e->validator->errors()->first();
        }

        return $e->getMessage();
    }
}
