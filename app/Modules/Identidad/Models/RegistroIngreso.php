<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un ingreso al sistema con el nombre que la persona escribió en el login
 * -- para cuentas que varias personas comparten (ver LoginForm::$nombre),
 * "quién entró" no lo dice la cuenta, lo dice este registro.
 *
 * @property int $id
 * @property int $user_id
 * @property ?string $session_id
 * @property string $nombre
 * @property ?string $ip_address
 * @property Carbon $iniciado_en
 * @property ?Carbon $finalizado_en
 */
class RegistroIngreso extends Model
{
    protected $table = 'registros_ingreso';

    protected $fillable = [
        'user_id',
        'session_id',
        'nombre',
        'ip_address',
        'iniciado_en',
        'finalizado_en',
    ];

    protected function casts(): array
    {
        return [
            'iniciado_en' => 'datetime',
            'finalizado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * En minutos, o null mientras el ingreso siga abierto (todavía no se
     * cerró sesión ni se revocó).
     */
    public function duracionEnMinutos(): ?int
    {
        if ($this->finalizado_en === null) {
            return null;
        }

        return (int) $this->iniciado_en->diffInMinutes($this->finalizado_en);
    }
}
