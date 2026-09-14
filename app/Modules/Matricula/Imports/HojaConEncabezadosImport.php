<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import genérico: solo captura las filas con sus columnas mapeadas por el
 * encabezado (primera fila). Las reglas de negocio de cada carga masiva
 * (estudiantes, matrícula) viven en el servicio correspondiente, no aquí.
 */
class HojaConEncabezadosImport implements ToCollection, WithHeadingRow
{
    /** @var Collection<int, Collection<string, mixed>> */
    public Collection $filas;

    public function __construct()
    {
        $this->filas = collect();
    }

    public function collection(Collection $filas): void
    {
        $this->filas = $filas;
    }
}
