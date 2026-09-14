<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Database\Factories\ReciboFactory;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $pago_id
 * @property SerieReciboEnum $serie
 * @property string $numero_recibo
 * @property Carbon $emitido_en
 * @property-read Pago $pago
 */
class Recibo extends Model implements HasMedia
{
    /** @use HasFactory<ReciboFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'recibos';

    protected $fillable = [
        'pago_id',
        'serie',
        'numero_recibo',
        'emitido_en',
    ];

    protected function casts(): array
    {
        return [
            'serie' => SerieReciboEnum::class,
            'emitido_en' => 'datetime',
        ];
    }

    protected static function newFactory(): ReciboFactory
    {
        return ReciboFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')->singleFile();
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }
}
