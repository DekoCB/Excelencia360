<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Models;

use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Seccion;
use App\Modules\Evaluaciones\Database\Factories\EvaluacionFactory;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Enums\TipoEvaluacionEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $horario_id
 * @property int $curso_virtual_id
 * @property int|null $seccion_id
 * @property string $nombre
 * @property TipoEvaluacionEnum $tipo
 * @property Carbon $fecha
 * @property string|null $enlace_externo
 * @property Carbon|null $disponible_hasta
 * @property EstadoEvaluacionEnum $estado
 * @property-read Horario $horario
 * @property-read CursoVirtual $cursoVirtual
 * @property-read Seccion|null $seccion
 */
class Evaluacion extends Model
{
    /** @use HasFactory<EvaluacionFactory> */
    use Auditable, HasFactory;

    protected $table = 'evaluaciones';

    protected $fillable = [
        'horario_id',
        'curso_virtual_id',
        'seccion_id',
        'nombre',
        'tipo',
        'fecha',
        'enlace_externo',
        'disponible_hasta',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEvaluacionEnum::class,
            'fecha' => 'date',
            'disponible_hasta' => 'datetime',
            'estado' => EstadoEvaluacionEnum::class,
        ];
    }

    protected static function newFactory(): EvaluacionFactory
    {
        return EvaluacionFactory::new();
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function cursoVirtual(): BelongsTo
    {
        return $this->belongsTo(CursoVirtual::class);
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    /**
     * @return HasMany<Calificacion, $this>
     */
    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class);
    }

    /**
     * @return HasMany<Pregunta, $this>
     */
    public function preguntas(): HasMany
    {
        return $this->hasMany(Pregunta::class)->orderBy('orden');
    }

    /**
     * @return HasMany<IntentoEvaluacion, $this>
     */
    public function intentos(): HasMany
    {
        return $this->hasMany(IntentoEvaluacion::class);
    }

    public function esVirtual(): bool
    {
        return $this->tipo === TipoEvaluacionEnum::VIRTUAL;
    }

    public function estaPublicada(): bool
    {
        return $this->estado === EstadoEvaluacionEnum::PUBLICADA;
    }

    public function tieneEnlaceExterno(): bool
    {
        return $this->enlace_externo !== null;
    }

    /**
     * El enlace externo solo puede resolverse si la evaluación ya está
     * publicada y, si el docente puso una fecha límite, todavía no pasó.
     */
    public function enlaceDisponible(): bool
    {
        if (! $this->estaPublicada() || ! $this->tieneEnlaceExterno()) {
            return false;
        }

        return $this->disponible_hasta === null || now()->lte($this->disponible_hasta);
    }
}
