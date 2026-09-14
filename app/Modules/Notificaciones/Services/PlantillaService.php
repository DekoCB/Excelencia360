<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Services;

use App\Models\User;
use App\Modules\Notificaciones\Models\PlantillaWhatsapp;
use Illuminate\Database\Eloquent\Collection;

class PlantillaService
{
    public function crear(string $nombre, string $contenido, User $creador): PlantillaWhatsapp
    {
        /** @var PlantillaWhatsapp $plantilla */
        $plantilla = PlantillaWhatsapp::query()->create([
            'nombre' => $nombre,
            'contenido' => $contenido,
            'activa' => true,
            'creado_por' => $creador->id,
        ]);

        return $plantilla;
    }

    public function actualizar(PlantillaWhatsapp $plantilla, string $nombre, string $contenido, bool $activa): PlantillaWhatsapp
    {
        $plantilla->update([
            'nombre' => $nombre,
            'contenido' => $contenido,
            'activa' => $activa,
        ]);

        return $plantilla;
    }

    /**
     * @return Collection<int, PlantillaWhatsapp>
     */
    public function todas(): Collection
    {
        return PlantillaWhatsapp::query()->latest('id')->get();
    }

    /**
     * @return Collection<int, PlantillaWhatsapp>
     */
    public function activas(): Collection
    {
        return PlantillaWhatsapp::query()->where('activa', true)->latest('id')->get();
    }
}
