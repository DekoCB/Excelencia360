<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MatriculaMasivaPlantillaExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['dni', 'grado', 'observaciones'];
    }

    public function array(): array
    {
        return [
            ['87654321', '1ro de Secundaria', ''],
            ['76543210', '2do de Secundaria', ''],
        ];
    }
}
