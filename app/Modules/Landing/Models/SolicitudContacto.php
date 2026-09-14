<?php

declare(strict_types=1);

namespace App\Modules\Landing\Models;

use App\Modules\Landing\Database\Factories\SolicitudContactoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mensajes enviados desde el formulario de contacto de la web pública.
 * Sin envío de correo real todavía (ver docs/DESPLIEGUE.md): por ahora
 * solo quedan guardados para que Dirección/Coordinador los revise.
 *
 * @property string $nombre
 * @property string $email
 * @property string $telefono
 * @property string|null $asunto
 * @property string $mensaje
 * @property bool $atendido
 */
class SolicitudContacto extends Model
{
    /** @use HasFactory<SolicitudContactoFactory> */
    use HasFactory;

    protected $table = 'solicitudes_contacto';

    protected $fillable = [
        'nombre',
        'email',
        'telefono',
        'asunto',
        'mensaje',
        'atendido',
    ];

    protected function casts(): array
    {
        return [
            'atendido' => 'boolean',
        ];
    }

    protected static function newFactory(): SolicitudContactoFactory
    {
        return SolicitudContactoFactory::new();
    }
}
