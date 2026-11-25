<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Pagos\Models\TipoConceptoPago;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TipoConceptoPagoService
{
    /**
     * @return Collection<int, TipoConceptoPago>
     */
    public function listar(): Collection
    {
        return TipoConceptoPago::query()->orderBy('nombre')->get();
    }

    /**
     * @return Collection<int, TipoConceptoPago>
     */
    public function activos(): Collection
    {
        return TipoConceptoPago::query()->where('activo', true)->orderBy('nombre')->get();
    }

    public function crear(string $nombre): TipoConceptoPago
    {
        return TipoConceptoPago::query()->create([
            'nombre' => $nombre,
            'slug' => $this->slugUnico($nombre),
            'activo' => true,
        ]);
    }

    /**
     * El slug no se puede cambiar una vez creado: es lo que usa el código
     * para los 2 casos de negocio especiales (ver
     * TipoConceptoPago::esMensualidad()/esOtro()) -- solo el nombre y el
     * estado activo se pueden editar.
     */
    public function actualizar(TipoConceptoPago $tipo, string $nombre, bool $activo): TipoConceptoPago
    {
        $tipo->update([
            'nombre' => $nombre,
            'activo' => $activo,
        ]);

        return $tipo->refresh();
    }

    private function slugUnico(string $nombre): string
    {
        $base = Str::slug($nombre);
        $slug = $base;
        $sufijo = 1;

        while (TipoConceptoPago::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$sufijo;
        }

        return $slug;
    }
}
