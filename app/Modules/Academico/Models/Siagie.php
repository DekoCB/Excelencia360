<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\SiagieFactory;
use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * El periodo SIAGIE del MINEDU (1.er periodo, 2.° periodo, Anual), por
 * año -- independiente del Grupo rotativo de CEBA (ver ModalidadCicloEnum,
 * un eje completamente aparte). Cada matrícula puede tener su propio
 * Siagie sin importar en qué Grupo esté (ver Matricula::siagie()).
 *
 * Solo el tipo ANUAL corresponde además a un Ciclo real con horarios
 * propios (ver ciclo()): 1.er y 2.° periodo son clasificación pura, sin
 * fechas obligatorias ni horarios asociados.
 *
 * @property int $id
 * @property TipoSiagieEnum $tipo
 * @property int $anio
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property EstadoCicloEnum $estado
 * @property-read Ciclo|null $ciclo
 */
class Siagie extends Model
{
    /** @use HasFactory<SiagieFactory> */
    use Auditable, HasFactory;

    protected $table = 'siagies';

    protected $fillable = [
        'tipo',
        'anio',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoSiagieEnum::class,
            'estado' => EstadoCicloEnum::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    protected static function newFactory(): SiagieFactory
    {
        return SiagieFactory::new();
    }

    /**
     * El Ciclo real (modalidad=anual) que le corresponde, si este Siagie
     * es de tipo ANUAL -- ahí es donde viven sus Horarios/Matrículas.
     *
     * @return HasOne<Ciclo, $this>
     */
    public function ciclo(): HasOne
    {
        return $this->hasOne(Ciclo::class);
    }

    /**
     * @return HasMany<Matricula, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    /**
     * Texto para mostrar (p. ej. "2026-1", "2026-2", "2026 Anual").
     */
    public function nombreCompleto(): string
    {
        return $this->tipo === TipoSiagieEnum::ANUAL
            ? "{$this->anio} Anual"
            : "{$this->anio}-{$this->numeroDePeriodo()}";
    }

    private function numeroDePeriodo(): string
    {
        return match ($this->tipo) {
            TipoSiagieEnum::PRIMERO => '1',
            TipoSiagieEnum::SEGUNDO => '2',
            TipoSiagieEnum::ANUAL => 'Anual',
        };
    }
}
