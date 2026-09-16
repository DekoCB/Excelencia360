<?php

declare(strict_types=1);

namespace App\Modules\Biblioteca\Models;

use App\Models\User;
use App\Modules\Biblioteca\Database\Factories\DescargaLibroFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un registro por cada vez que alguien descarga el PDF de un libro (ver
 * BibliotecaService::registrarDescarga()) -- es en sí mismo un log de
 * auditoría de lectura, así que no usa el trait Auditable (mismo criterio
 * que RegistroIngreso: auditar la auditoría no aporta nada).
 *
 * @property int $id
 * @property int $libro_id
 * @property int $user_id
 * @property Carbon $descargado_en
 * @property-read Libro $libro
 * @property-read User $usuario
 */
class DescargaLibro extends Model
{
    /** @use HasFactory<DescargaLibroFactory> */
    use HasFactory;

    protected $table = 'descargas_libro';

    public $timestamps = false;

    protected $fillable = [
        'libro_id',
        'user_id',
        'descargado_en',
    ];

    protected function casts(): array
    {
        return [
            'descargado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): DescargaLibroFactory
    {
        return DescargaLibroFactory::new();
    }

    public function libro(): BelongsTo
    {
        return $this->belongsTo(Libro::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
