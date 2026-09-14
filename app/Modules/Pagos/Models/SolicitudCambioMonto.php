<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Models\User;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Database\Factories\SolicitudCambioMontoFactory;
use App\Modules\Pagos\Enums\EstadoSolicitudCambioMontoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $concepto_pago_id
 * @property float $monto_actual
 * @property float $monto_propuesto
 * @property EstadoSolicitudCambioMontoEnum $estado
 * @property int|null $solicitado_por
 * @property Carbon $fecha_solicitud
 * @property int|null $aprobado_por
 * @property Carbon|null $fecha_resolucion
 * @property string|null $motivo_rechazo
 * @property-read ConceptoPago $concepto
 */
class SolicitudCambioMonto extends Model
{
    /** @use HasFactory<SolicitudCambioMontoFactory> */
    use Auditable, HasFactory;

    protected $table = 'solicitudes_cambio_monto';

    protected $fillable = [
        'concepto_pago_id',
        'monto_actual',
        'monto_propuesto',
        'estado',
        'solicitado_por',
        'fecha_solicitud',
        'aprobado_por',
        'fecha_resolucion',
        'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'monto_actual' => 'decimal:2',
            'monto_propuesto' => 'decimal:2',
            'estado' => EstadoSolicitudCambioMontoEnum::class,
            'fecha_solicitud' => 'datetime',
            'fecha_resolucion' => 'datetime',
        ];
    }

    protected static function newFactory(): SolicitudCambioMontoFactory
    {
        return SolicitudCambioMontoFactory::new();
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoPago::class, 'concepto_pago_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
