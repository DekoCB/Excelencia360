<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Database\Factories\MatriculaFactory;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property int $ciclo_id
 * @property int $grado_id
 * @property Carbon $fecha_matricula
 * @property Carbon|null $fecha_fin_estudio
 * @property EstadoMatriculaEnum $estado
 * @property-read Estudiante|null $estudiante
 * @property-read Ciclo $ciclo
 * @property-read Grado $grado
 */
class Matricula extends Model implements HasMedia
{
    /** @use HasFactory<MatriculaFactory> */
    use Auditable, HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'estudiante_id',
        'ciclo_id',
        'grado_id',
        'fecha_matricula',
        'fecha_fin_estudio',
        'estado',
        'observaciones',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_matricula' => 'date',
            'fecha_fin_estudio' => 'date',
            'estado' => EstadoMatriculaEnum::class,
        ];
    }

    protected static function newFactory(): MatriculaFactory
    {
        return MatriculaFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('ficha')->singleFile();
        $this->addMediaCollection('constancia')->singleFile();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    /**
     * Los horarios (cursos) específicos que se le asignaron explícitamente
     * a esta matrícula -- ver MatriculaService::asignarHorarioDeCurso() y
     * scopeDelHorario() para cómo se usa esto en la práctica.
     *
     * @return BelongsToMany<Horario, $this>
     */
    public function horarios(): BelongsToMany
    {
        return $this->belongsToMany(Horario::class, 'matricula_horario');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Las matrículas que cuentan para un horario dado: mismo grado y
     * ciclo, aprobadas.
     *
     * Por defecto, matricularse en un semestre matricula automáticamente
     * en TODOS sus cursos (coincidir por grado+ciclo ya basta). Pero en
     * cuanto una matrícula tiene AL MENOS UNA asignación explícita en
     * matricula_horario (ver MatriculaService::asignarHorarioDeCurso()),
     * deja de ser "todo el semestre" y pasa a ser "solo estos cursos": ya
     * no cuenta para ningún horario que no esté entre sus asignaciones,
     * aunque sea del mismo grado y ciclo. Esto también cubre el caso
     * anterior de cursos con paralelos (dos Horario del mismo
     * curso_id+grado_id+ciclo_id): ahí la asignación explícita decide a
     * cuál sección pertenece cada estudiante.
     */
    public function scopeDelHorario(Builder $query, Horario $horario): Builder
    {
        return $query->where('grado_id', $horario->grado_id)
            ->where('ciclo_id', $horario->ciclo_id)
            ->where('estado', 'aprobada')
            ->where(function (Builder $q) use ($horario) {
                $q->whereDoesntHave('horarios')
                    ->orWhereHas('horarios', fn (Builder $qq) => $qq->where('horarios.id', $horario->id));
            });
    }
}
