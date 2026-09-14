<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Database\Factories\CargoAdicionalFactory;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un cobro futuro puntual para un estudiante en particular (Convalidación,
 * Exoneración, Recuperación, Visación, etc.) -- concepto y monto libres,
 * a diferencia del catálogo compartido ConceptoPago. Mismo patrón que
 * Cuota: montoPagado()/saldoPendiente() se calculan desde los Pagos
 * aprobados reales, nunca desde el monto nominal.
 *
 * @property int $id
 * @property int $estudiante_id
 * @property string $concepto
 * @property float $monto
 * @property EstadoCuotaEnum $estado
 * @property int|null $registrado_por
 * @property-read Estudiante $estudiante
 */
class CargoAdicional extends Model
{
    /** @use HasFactory<CargoAdicionalFactory> */
    use Auditable, HasFactory;

    protected $table = 'cargos_adicionales';

    protected $fillable = [
        'estudiante_id',
        'concepto',
        'monto',
        'estado',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'estado' => EstadoCuotaEnum::class,
        ];
    }

    protected static function newFactory(): CargoAdicionalFactory
    {
        return CargoAdicionalFactory::new();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * @return HasMany<Pago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * Suma de los pagos ya aprobados vinculados a este cargo -- puede
     * cubrirse en varios pagos parciales, igual que Cuota::montoPagado().
     */
    public function montoPagado(): float
    {
        return (float) $this->pagos()->where('estado', EstadoPagoEnum::APROBADO)->sum('monto');
    }

    /**
     * Lo que falta por cobrar de este cargo. Nunca negativo.
     */
    public function saldoPendiente(): float
    {
        return max(0.0, (float) $this->monto - $this->montoPagado());
    }
}
