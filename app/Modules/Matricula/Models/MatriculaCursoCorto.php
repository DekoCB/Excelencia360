<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Database\Factories\MatriculaCursoCortoFactory;
use App\Modules\Matricula\Enums\EstadoMatriculaCursoCortoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Matrícula de "curso corto": un estudiante que solo lleva un curso de
 * capacitación individual, sin semestre ni programa de estudio -- ver la
 * migración de creación de `matriculas_curso_corto` para el porqué de que
 * sea un modelo aparte de `Matricula`.
 *
 * @property int $id
 * @property int $estudiante_id
 * @property int $curso_id
 * @property Carbon $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property EstadoMatriculaCursoCortoEnum $estado
 * @property string|null $observaciones
 * @property-read Estudiante $estudiante
 * @property-read Curso $curso
 */
class MatriculaCursoCorto extends Model
{
    /** @use HasFactory<MatriculaCursoCortoFactory> */
    use Auditable, HasFactory;

    protected $table = 'matriculas_curso_corto';

    protected $fillable = [
        'estudiante_id',
        'curso_id',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'observaciones',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'estado' => EstadoMatriculaCursoCortoEnum::class,
        ];
    }

    protected static function newFactory(): MatriculaCursoCortoFactory
    {
        return MatriculaCursoCortoFactory::new();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
