<?php

declare(strict_types=1);

namespace App\Modules\FlujoCaja\Models;

use App\Models\User;
use App\Modules\FlujoCaja\Database\Factories\EgresoFactory;
use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Un egreso registrado a mano (alquiler, servicios, planilla, etc.) --
 * junto con los Pago aprobados (ingresos), es lo que arma el libro de
 * movimientos de FlujoCajaService::movimientosDelPeriodo().
 *
 * @property int $id
 * @property CategoriaEgresoEnum $categoria
 * @property string|null $descripcion
 * @property float $monto
 * @property MetodoPagoEnum $metodo
 * @property Carbon $fecha_egreso
 * @property int|null $registrado_por
 */
class Egreso extends Model implements HasMedia
{
    /** @use HasFactory<EgresoFactory> */
    use HasFactory, InteractsWithMedia;

    protected $table = 'egresos';

    protected $fillable = [
        'categoria',
        'descripcion',
        'monto',
        'metodo',
        'fecha_egreso',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaEgresoEnum::class,
            'metodo' => MetodoPagoEnum::class,
            'fecha_egreso' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    protected static function newFactory(): EgresoFactory
    {
        return EgresoFactory::new();
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('comprobante')->singleFile();
    }
}
