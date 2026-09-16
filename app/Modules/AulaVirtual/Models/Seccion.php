<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Modules\AulaVirtual\Database\Factories\SeccionFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un bloque de contenido dentro de un curso virtual, estilo Moodle: con
 * nombre propio ("Bienvenida", "Fin de curso"), con una fecha real de
 * sesión que el docente elige a mano, o ambos -- reemplaza al entero
 * "semana" que tenían antes Material/ClaseGrabada/Tarea/Foro (sin ningún
 * vínculo real a fechas ni al horario).
 *
 * @property int $id
 * @property int $curso_virtual_id
 * @property string|null $nombre
 * @property Carbon|null $fecha
 * @property int $orden
 */
class Seccion extends Model
{
    /** @use HasFactory<SeccionFactory> */
    use Auditable, HasFactory;

    protected $table = 'secciones';

    protected $fillable = [
        'curso_virtual_id',
        'nombre',
        'fecha',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    protected static function newFactory(): SeccionFactory
    {
        return SeccionFactory::new();
    }

    public function cursoVirtual(): BelongsTo
    {
        return $this->belongsTo(CursoVirtual::class);
    }

    /**
     * Texto para mostrar como encabezado del bloque: el nombre si lo
     * tiene: si no, la fecha formateada (nunca deberían faltar los dos a
     * la vez -- ver SeccionService::crear()).
     */
    public function titulo(): string
    {
        if ($this->nombre !== null) {
            return $this->nombre;
        }

        return $this->fecha?->translatedFormat('l d \d\e F') ?? 'Sección';
    }
}
