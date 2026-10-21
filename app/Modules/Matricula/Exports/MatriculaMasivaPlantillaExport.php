<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MatriculaMasivaPlantillaExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['dni', 'carrera', 'semestre', 'curso', 'observaciones'];
    }

    public function array(): array
    {
        return [
            ['87654321', 'Administración de Empresas', 'Semestre 1', '', ''],
            ['76543210', 'Administración de Empresas', 'Semestre 1', 'Contabilidad General', ''],
        ];
    }
}
