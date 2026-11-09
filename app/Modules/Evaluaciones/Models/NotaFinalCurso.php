<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Models;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Database\Factories\NotaFinalCursoFactory;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota final editada a mano por el docente -- ver LibretaService::guardarNotaFinal()
 * y resumenPorCursos() para cómo gana sobre el promedio calculado de las
 * evaluaciones en cuanto existe.
 *
 * @property int $id
 * @property int $estudiante_id
 * @property int $horario_id
 * @property float $nota
 * @property int|null $editado_por
 * @property-read Estudiante|null $estudiante
 * @property-read Horario $horario
 * @property-read User|null $editadoPor
 */
class NotaFinalCurso extends Model
{
    /** @use HasFactory<NotaFinalCursoFactory> */
    use Auditable, HasFactory;

    protected $table = 'notas_finales_curso';

    protected $fillable = [
        'estudiante_id',
        'horario_id',
        'nota',
        'editado_por',
    ];

    protected function casts(): array
    {
        return [
            'nota' => 'decimal:2',
        ];
    }

    protected static function newFactory(): NotaFinalCursoFactory
    {
        return NotaFinalCursoFactory::new();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function editadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editado_por');
    }
}
