<?php

declare(strict_types=1);

namespace App\Modules\Personal\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PersonalPlantillaExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'nombres', 'apellidos', 'dni', 'celular',
            'cargo', 'area', 'fecha_ingreso',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Rosa', 'Mendoza Díaz', '41234567', '987654321',
                'Psicóloga', 'Bienestar Estudiantil', '01/03/2024',
            ],
            [
                'Jorge', 'Salazar Vega', '71234567', '',
                'Portero', '', '',
            ],
        ];
    }
}
