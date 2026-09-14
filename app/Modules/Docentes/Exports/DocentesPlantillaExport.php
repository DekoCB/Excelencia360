<?php

declare(strict_types=1);

namespace App\Modules\Docentes\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DocentesPlantillaExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'nombres', 'apellidos', 'dni', 'celular',
            'especialidad', 'grado_academico', 'fecha_ingreso',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Ana', 'Torres Quispe', '45678912', '987654321',
                'Matemática', 'Licenciado', '01/03/2024',
            ],
            [
                'Luis', 'Ramírez Soto', '78912345', '',
                '', '', '',
            ],
        ];
    }
}
