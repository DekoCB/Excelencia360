<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\CicloFactory;
use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\EstadoPeriodoMatriculaEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property Carbon|null $fecha_inicio_matricula
 * @property Carbon|null $fecha_fin_matricula
 * @property EstadoPeriodoMatriculaEnum|null $estado_matricula
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
        'fecha_inicio_matricula',
        'fecha_fin_matricula',
        'estado_matricula',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCicloEnum::class,
            'modalidad' => ModalidadCicloEnum::class,
            'estado' => EstadoCicloEnum::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'fecha_inicio_matricula' => 'date',
            'fecha_fin_matricula' => 'date',
            'estado_matricula' => EstadoPeriodoMatriculaEnum::class,
        ];
    }

    protected static function newFactory(): CicloFactory
    {
        return CicloFactory::new();
    }

    /**
     * @return HasMany<Horario, $this>
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }
}
