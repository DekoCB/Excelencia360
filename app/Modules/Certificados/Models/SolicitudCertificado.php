<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Models;

use App\Models\User;
use App\Modules\Certificados\Database\Factories\SolicitudCertificadoFactory;
use App\Modules\Certificados\Enums\EstadoSolicitudCertificadoEnum;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\MetodoEntregaEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property TipoDocumentoEnum $tipo
 * @property int|null $matricula_id
 * @property string $motivo
 * @property EstadoSolicitudCertificadoEnum $estado
 * @property int|null $atendido_por
 * @property int|null $certificado_id
 * @property int|null $libreta_id
 * @property string|null $motivo_rechazo
 * @property MetodoEntregaEnum|null $metodo_entrega
 * @property string|null $correo_entrega
 * @property-read Estudiante|null $estudiante
 * @property-read Matricula|null $matricula
 * @property-read User|null $revisor
 * @property-read Certificado|null $certificado
 * @property-read Libreta|null $libreta
 */
class SolicitudCertificado extends Model implements HasMedia
{
    /** @use HasFactory<SolicitudCertificadoFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'solicitudes_certificado';

    protected $fillable = [
        'estudiante_id',
        'tipo',
        'matricula_id',
        'motivo',
        'estado',
        'atendido_por',
        'certificado_id',
        'libreta_id',
        'motivo_rechazo',
        'metodo_entrega',
        'correo_entrega',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoEnum::class,
            'estado' => EstadoSolicitudCertificadoEnum::class,
            'metodo_entrega' => MetodoEntregaEnum::class,
        ];
    }

    protected static function newFactory(): SolicitudCertificadoFactory
    {
        return SolicitudCertificadoFactory::new();
    }

    /**
     * Los requisitos que el estudiante sube al solicitar (DNI, partida,
     * etc.): varios archivos por solicitud, a diferencia del PDF único del
     * certificado ya emitido.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('requisitos');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(Certificado::class);
    }

    public function libreta(): BelongsTo
    {
        return $this->belongsTo(Libreta::class);
    }
}
