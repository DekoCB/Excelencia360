<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Pagos\Database\Factories\PagoParteFactory;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pago_id
 * @property float $monto
 * @property MetodoPagoEnum $metodo
 * @property string|null $nota
 * @property-read Pago $pago
 */
class PagoParte extends Model
{
    /** @use HasFactory<PagoParteFactory> */
    use HasFactory;

    protected $table = 'pago_partes';

    protected $fillable = [
        'pago_id',
        'monto',
        'metodo',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'metodo' => MetodoPagoEnum::class,
        ];
    }

    protected static function newFactory(): PagoParteFactory
    {
        return PagoParteFactory::new();
    }

    /**
     * @return BelongsTo<Pago, $this>
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /**
     * El método para mostrar, con su nota pegada al lado si tiene una --
     * ej. "Yape Walter" en vez de solo "Yape". El método en sí (para
     * reportes/filtros) sigue siendo $this->metodo, sin tocar.
     */
    public function metodoConNota(): string
    {
        $nota = trim((string) $this->nota);

        return $nota === '' ? $this->metodo->label() : "{$this->metodo->label()} {$nota}";
    }
}
