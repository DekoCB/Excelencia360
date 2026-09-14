<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Database\Factories\CuentaBancariaFactory;
use App\Modules\Pagos\Enums\MedioCuentaEnum;
use App\Modules\Pagos\Enums\TipoBilleteraEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property MedioCuentaEnum $medio
 * @property string|null $banco
 * @property string|null $numero_cuenta
 * @property string|null $cci
 * @property TipoBilleteraEnum|null $tipo_billetera
 * @property string|null $celular
 * @property string $titular
 * @property bool $activa
 */
class CuentaBancaria extends Model implements HasMedia
{
    /** @use HasFactory<CuentaBancariaFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'medio',
        'banco',
        'numero_cuenta',
        'cci',
        'tipo_billetera',
        'celular',
        'titular',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'medio' => MedioCuentaEnum::class,
            'tipo_billetera' => TipoBilleteraEnum::class,
            'activa' => 'boolean',
        ];
    }

    protected static function newFactory(): CuentaBancariaFactory
    {
        return CuentaBancariaFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('qr')->singleFile();
    }
}
