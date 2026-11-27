<?php

declare(strict_types=1);

namespace App\Modules\CajaChica\Models;

use App\Models\User;
use App\Modules\CajaChica\Database\Factories\CajaChicaMovimientoFactory;
use App\Modules\CajaChica\Enums\TipoMovimientoCajaChicaEnum;
use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Un movimiento del fondo de caja chica: la asignación inicial, una
 * reposición (sube el fondo cuando se agota) o un gasto menor (baja el
 * fondo) -- ver TipoMovimientoCajaChicaEnum::sumaAlSaldo().
 *
 * @property int $id
 * @property TipoMovimientoCajaChicaEnum $tipo
 * @property CategoriaEgresoEnum|null $categoria
 * @property string|null $descripcion
 * @property float $monto
 * @property Carbon $fecha
 * @property int|null $registrado_por
 * @property-read User|null $registradoPor
 */
class CajaChicaMovimiento extends Model implements HasMedia
{
    /** @use HasFactory<CajaChicaMovimientoFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'caja_chica_movimientos';

    protected $fillable = [
        'tipo',
        'categoria',
        'descripcion',
        'monto',
        'fecha',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoCajaChicaEnum::class,
            'categoria' => CategoriaEgresoEnum::class,
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    protected static function newFactory(): CajaChicaMovimientoFactory
    {
        return CajaChicaMovimientoFactory::new();
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
