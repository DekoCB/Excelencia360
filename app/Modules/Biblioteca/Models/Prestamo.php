<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Models;

use App\Models\User;
use App\Modules\Biblioteca\Database\Factories\PrestamoFactory;
use App\Modules\Biblioteca\Enums\EstadoPrestamoEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ejemplar_id
 * @property int $solicitante_id
 * @property int $entregado_por
 * @property Carbon $fecha_prestamo
 * @property Carbon $fecha_devolucion_esperada
 * @property Carbon|null $fecha_devolucion_real
 * @property EstadoPrestamoEnum $estado
 * @property string|null $observaciones
 * @property-read Ejemplar $ejemplar
 * @property-read User $solicitante
 * @property-read User $entregadoPor
 */
class Prestamo extends Model
{
    /** @use HasFactory<PrestamoFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'ejemplar_id',
        'solicitante_id',
        'entregado_por',
        'fecha_prestamo',
        'fecha_devolucion_esperada',
        'fecha_devolucion_real',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_prestamo' => 'date',
            'fecha_devolucion_esperada' => 'date',
            'fecha_devolucion_real' => 'date',
            'estado' => EstadoPrestamoEnum::class,
        ];
    }

    protected static function newFactory(): PrestamoFactory
    {
        return PrestamoFactory::new();
    }

    public function ejemplar(): BelongsTo
    {
        return $this->belongsTo(Ejemplar::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    /**
     * Vencido = sigue prestado y ya pasó la fecha esperada de devolución.
     * No es un estado guardado aparte -- se deriva, para no necesitar un
     * job que lo actualice todos los días.
     */
    public function estaVencido(): bool
    {
        return $this->estado === EstadoPrestamoEnum::PRESTADO
            && $this->fecha_devolucion_esperada->isPast()
            && ! $this->fecha_devolucion_esperada->isToday();
    }
}
