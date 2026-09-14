<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReporteExport implements FromArray, WithHeadings
{
    /**
     * @param  list<string>  $columnas
     * @param  list<array<int, string|int|float>>  $filas
     */
    public function __construct(
        private readonly array $columnas,
        private readonly array $filas,
    ) {}

    public function array(): array
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return $this->columnas;
    }
}
