<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\CicloFactory;
use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property int $anio
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property ?TipoCicloEnum $tipo
 * @property ModalidadCicloEnum $modalidad
 * @property EstadoCicloEnum $estado
 * @property int|null $siagie_id
 * @property-read Siagie|null $siagie
 */
class Ciclo extends Model
{
    /** @use HasFactory<CicloFactory> */
    use Auditable, HasFactory;

    /**
     * Default en memoria (no solo a nivel de columna): Eloquent no relee
     * el DEFAULT de la BD después de create(), así que sin esto
     * $ciclo->modalidad quedaría null para cualquier código (factories,
     * seeders, CicloService::crear() sin la clave) que no la pase
     * explícitamente.
     */
    protected $attributes = [
        'modalidad' => 'seis_meses',
    ];

    protected $fillable = [
        'nombre',
        'tipo',
        'modalidad',
        'anio',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'siagie_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCicloEnum::class,
            'modalidad' => ModalidadCicloEnum::class,
            'estado' => EstadoCicloEnum::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    protected static function newFactory(): CicloFactory
    {
        return CicloFactory::new();
    }

    /**
     * @return HasMany<PeriodoMatricula, $this>
     */
    public function periodosMatricula(): HasMany
    {
        return $this->hasMany(PeriodoMatricula::class);
    }

    /**
     * @return HasMany<Horario, $this>
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /**
     * Solo tiene valor en el (a lo sumo un) Ciclo modalidad=anual de cada
     * año: lo clasifica dentro de SIAGIE. Vacaciones/Evaluaciones lo usan
     * en vez de comparar modalidad directamente (ver VacacionService,
     * EvaluacionService, LibretaService).
     */
    public function siagie(): BelongsTo
    {
        return $this->belongsTo(Siagie::class);
    }
}
