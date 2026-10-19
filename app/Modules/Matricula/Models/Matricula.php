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
     * Los horarios específicos (uno por curso, como máximo) que se le
     * asignaron explícitamente a esta matrícula -- solo hace falta usarlo
     * cuando un curso tiene varias secciones/paralelos; ver
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
     * ciclo, aprobadas. El aula (Grupo A/B) ya no hace falta compararla
     * aparte -- la determina el grado (ver Grado::letraAula()), siempre
     * igual dentro de un mismo grado y ciclo, así que coincidir por
     * grado_id ya basta.
     *
     * Cuando el curso de este horario tiene paralelos (otro Horario con el
     * mismo curso_id+grado_id+ciclo_id), esa coincidencia por grado+ciclo
     * ya no basta para saber a cuál sección pertenece cada estudiante: ahí
     * además se exige la asignación explícita en matricula_horario. Si el
     * curso no tiene paralelos, nadie necesita asignación (sigue siendo
     * automático, como antes).
     */
    public function scopeDelHorario(Builder $query, Horario $horario): Builder
    {
        $tieneParalelos = Horario::query()
            ->where('curso_id', $horario->curso_id)
            ->where('grado_id', $horario->grado_id)
            ->where('ciclo_id', $horario->ciclo_id)
            ->where('id', '!=', $horario->id)
            ->exists();

        return $query->where('grado_id', $horario->grado_id)
            ->where('ciclo_id', $horario->ciclo_id)
            ->where('estado', 'aprobada')
            ->when(
                $tieneParalelos,
                fn (Builder $q) => $q->whereHas('horarios', fn (Builder $qq) => $qq->where('horarios.id', $horario->id)),
            );
    }
}
