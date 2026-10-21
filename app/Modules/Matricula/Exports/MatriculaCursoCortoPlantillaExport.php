<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MatriculaCursoCortoPlantillaExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['dni', 'curso'];
    }

    public function array(): array
    {
        return [
            ['87654321', 'Ofimática Básica'],
            ['76543210', 'Ofimática Básica'],
        ];
    }
}
