<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Database\Factories\ConceptoPagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property int $tipo_concepto_pago_id
 * @property float $monto_base
 * @property bool $activo
 * @property-read TipoConceptoPago $tipoConceptoPago
 */
class ConceptoPago extends Model
{
    /** @use HasFactory<ConceptoPagoFactory> */
    use Auditable, HasFactory;

    protected $table = 'conceptos_pago';

    protected $fillable = [
        'nombre',
        'tipo_concepto_pago_id',
        'monto_base',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'monto_base' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): ConceptoPagoFactory
    {
        return ConceptoPagoFactory::new();
    }

    public function tipoConceptoPago(): BelongsTo
    {
        return $this->belongsTo(TipoConceptoPago::class);
    }

    /**
     * @return HasMany<SolicitudCambioMonto, $this>
     */
    public function solicitudesCambioMonto(): HasMany
    {
        return $this->hasMany(SolicitudCambioMonto::class);
    }

    /**
     * Reemplaza las comparaciones que antes eran
     * `$concepto->tipo === TipoConceptoEnum::MENSUALIDAD` -- la Mensualidad
     * se cobra contra Cuotas de un PlanPago (ver CobranzaService), a
     * diferencia de un concepto "libre" (Matrícula, Certificado, etc.).
     */
    public function esMensualidad(): bool
    {
        return $this->tipoConceptoPago->esMensualidad();
    }

    /**
     * "Otro" exige un detalle en texto libre al registrar el pago (ver
     * pagos/index.blade.php) -- ningún otro tipo lo pide.
     */
    public function esOtro(): bool
    {
        return $this->tipoConceptoPago->esOtro();
    }
}
