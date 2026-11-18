<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\SeccionAcademicaFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Etiqueta de reporte/filtro (A-E por ahora, ampliable desde el panel):
 * no determina aula ni horario, a diferencia de la antigua "Sección A/B"
 * -- ver Matricula::$fillable (seccion_academica_id) y el detalle de
 * Programa de Estudio, los dos lugares donde se usa.
 *
 * Se llama "SeccionAcademica" (tabla "secciones_academicas") para no
 * chocar con AulaVirtual\Models\Seccion (tabla "secciones"), los bloques
 * de contenido del aula virtual -- un concepto completamente distinto.
 *
 * @property int $id
 * @property string $letra
 * @property bool $activa
 */
class SeccionAcademica extends Model
{
    /** @use HasFactory<SeccionAcademicaFactory> */
    use Auditable, HasFactory;

    protected $table = 'secciones_academicas';

    protected $fillable = [
        'letra',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    protected static function newFactory(): SeccionAcademicaFactory
    {
        return SeccionAcademicaFactory::new();
    }
}
