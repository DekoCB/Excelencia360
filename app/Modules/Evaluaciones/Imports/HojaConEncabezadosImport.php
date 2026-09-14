<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import genérico: solo captura las filas con sus columnas mapeadas por el
 * encabezado (primera fila). Las reglas de negocio de la importación de
 * notas viven en EvaluacionService::calificarDesdeFilas(), no aquí (mismo
 * patrón que App\Modules\Matricula\Imports\HojaConEncabezadosImport).
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
