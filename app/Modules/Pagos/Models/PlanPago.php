<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Database\Factories\PlanPagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $matricula_id
 * @property int $numero_cuotas
 * @property float $monto_total
 * @property-read Matricula|null $matricula
 */
class PlanPago extends Model
{
    /** @use HasFactory<PlanPagoFactory> */
    use Auditable, HasFactory;

    protected $table = 'planes_pago';

    protected $fillable = [
        'matricula_id',
        'numero_cuotas',
        'monto_total',
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
        ];
    }

    protected static function newFactory(): PlanPagoFactory
    {
        return PlanPagoFactory::new();
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    /**
     * @return HasMany<Cuota, $this>
     */
    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class)->orderBy('numero');
    }
}
