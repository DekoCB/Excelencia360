<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Services;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\ExamenUbicacion;

class ExamenUbicacionService
{
    /**
     * @param  array{fecha: string, costo: float, resultado: ?string, grado_asignado_id: ?int, observaciones: ?string}  $datos
     */
    public function registrar(Estudiante $estudiante, array $datos): ExamenUbicacion
    {
        return $estudiante->examenesUbicacion()->create($datos);
    }
}
