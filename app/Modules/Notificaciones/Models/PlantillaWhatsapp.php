<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Models;

use App\Models\User;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Notificaciones\Database\Factories\PlantillaWhatsappFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $nombre
 * @property string $contenido
 * @property bool $activa
 * @property int|null $creado_por
 * @property-read User|null $creador
 */
class PlantillaWhatsapp extends Model
{
    /** @use HasFactory<PlantillaWhatsappFactory> */
    use Auditable, HasFactory;

    protected $table = 'plantillas_whatsapp';

    protected $fillable = [
        'nombre',
        'contenido',
        'activa',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    protected static function newFactory(): PlantillaWhatsappFactory
    {
        return PlantillaWhatsappFactory::new();
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /**
     * Sustituye placeholders {{clave}} por el valor correspondiente en
     * $variables. Las claves sin valor provisto quedan sin reemplazar.
     *
     * @param  array<string, string>  $variables
     */
    public function renderizar(array $variables): string
    {
        $contenido = $this->contenido;

        foreach ($variables as $clave => $valor) {
            $contenido = str_replace('{{'.$clave.'}}', $valor, $contenido);
        }

        return $contenido;
    }
}
