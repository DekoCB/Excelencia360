<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Database\Factories\TipoConceptoPagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reemplaza al antiguo TipoConceptoEnum (6 valores fijos) como una tabla
 * gestionable desde UI -- el nombre es libre, pero `slug` es estable y no
 * se edita desde el CRUD: el código se apoya en él para los 2 casos de
 * negocio especiales (Mensualidad, Otro), ver esMensualidad()/esOtro().
 *
 * @property int $id
 * @property string $nombre
 * @property string $slug
 * @property bool $activo
 */
class TipoConceptoPago extends Model
{
    /** @use HasFactory<TipoConceptoPagoFactory> */
    use Auditable, HasFactory;

    protected $table = 'tipos_concepto_pago';

    protected $fillable = [
        'nombre',
        'slug',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): TipoConceptoPagoFactory
    {
        return TipoConceptoPagoFactory::new();
    }

    /**
     * @return HasMany<ConceptoPago, $this>
     */
    public function conceptos(): HasMany
    {
        return $this->hasMany(ConceptoPago::class);
    }

    public function esMensualidad(): bool
    {
        return $this->slug === 'mensualidad';
    }

    public function esOtro(): bool
    {
        return $this->slug === 'otro';
    }
}
