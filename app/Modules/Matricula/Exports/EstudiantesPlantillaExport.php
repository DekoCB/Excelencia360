<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EstudiantesPlantillaExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'nombres', 'apellidos', 'dni', 'fecha_nacimiento', 'estado_civil',
            'direccion', 'celular', 'observaciones',
            'apoderado_nombres', 'apoderado_dni', 'apoderado_celular', 'apoderado_correo',
            'apoderado_direccion', 'apoderado_parentesco',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Juana', 'Pérez Ríos', '87654321', '15/03/2010', '',
                'Av. Los Álamos 123', '987654321', 'Fila de ejemplo: bórrala antes de subir el archivo.',
                'Rosa Ríos Gómez', '12345678', '912345678', 'rosa@example.com',
                'Av. Los Álamos 123', 'Madre',
            ],
            [
                'Carlos', 'Gómez Luna', '76543210', '20/06/1990', 'soltero',
                '', '923456789', '',
                '', '', '', '',
                '', '',
            ],
        ];
    }
}
