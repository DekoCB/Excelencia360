<?php

declare(strict_types=1);

namespace App\Modules\Tramites\Models;

use App\Models\User;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Tramites\Database\Factories\SolicitudTramiteFactory;
use App\Modules\Tramites\Enums\CategoriaTramiteEnum;
use App\Modules\Tramites\Enums\EstadoTramiteEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * FUT: una solicitud administrativa genérica de un miembro de la
 * institución (ver la migración para el porqué de este módulo aparte de
 * SolicitudCertificado/SolicitudCambioMonto).
 *
 * @property int $id
 * @property int $solicitante_id
 * @property CategoriaTramiteEnum $categoria
 * @property string $asunto
 * @property string $descripcion
 * @property EstadoTramiteEnum $estado
 * @property int|null $responsable_id
 * @property string|null $resolucion
 * @property Carbon|null $atendido_en
 * @property-read User $solicitante
 * @property-read User|null $responsable
 */
class SolicitudTramite extends Model implements HasMedia
{
    /** @use HasFactory<SolicitudTramiteFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'solicitudes_tramite';

    protected $fillable = [
        'solicitante_id',
        'categoria',
        'asunto',
        'descripcion',
        'estado',
        'responsable_id',
        'resolucion',
        'atendido_en',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaTramiteEnum::class,
            'estado' => EstadoTramiteEnum::class,
            'atendido_en' => 'datetime',
        ];
    }

    protected static function newFactory(): SolicitudTramiteFactory
    {
        return SolicitudTramiteFactory::new();
    }

    /**
     * Documentos que el solicitante adjunta (cualquier cantidad, a
     * diferencia del PDF único de un certificado ya emitido) -- mismo
     * criterio que SolicitudCertificado::registerMediaCollections().
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('adjuntos');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
