<?php

declare(strict_types=1);

namespace App\Modules\FlujoCaja\Models;

use App\Models\User;
use App\Modules\FlujoCaja\Database\Factories\TurnoCajaFactory;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Models\Pago;
use App\Shared\Enums\TurnoDiaEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un turno de caja: el cajero lo abre con un monto inicial en caja, trabaja
 * durante su jornada (cada Pago que registra y cada Egreso que anota quedan
 * ligados a este turno, ver FlujoCajaService::turnoAbiertoDe()), y lo
 * cierra declarando cuánto cuenta en caja al final -- el sistema calcula
 * cuánto debería haber (monto_inicial + ingresos - egresos del turno, ver
 * FlujoCajaService::montoCalculadoDelTurno()) para comparar contra lo
 * declarado.
 *
 * @property int $id
 * @property int $usuario_id
 * @property TurnoDiaEnum|null $turno
 * @property Carbon $fecha
 * @property Carbon $hora_apertura
 * @property float $monto_inicial
 * @property Carbon|null $hora_cierre
 * @property float|null $monto_final_declarado
 * @property string $estado 'abierto'|'cerrado'
 * @property-read User $usuario
 */
class TurnoCaja extends Model
{
    /** @use HasFactory<TurnoCajaFactory> */
    use Auditable, HasFactory;

    protected $table = 'turnos_caja';

    protected $fillable = [
        'usuario_id',
        'turno',
        'fecha',
        'hora_apertura',
        'monto_inicial',
        'hora_cierre',
        'monto_final_declarado',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'turno' => TurnoDiaEnum::class,
            'fecha' => 'date',
            'hora_apertura' => 'datetime',
            'hora_cierre' => 'datetime',
            'monto_inicial' => 'decimal:2',
            'monto_final_declarado' => 'decimal:2',
        ];
    }

    protected static function newFactory(): TurnoCajaFactory
    {
        return TurnoCajaFactory::new();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Pago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto';
    }
}
