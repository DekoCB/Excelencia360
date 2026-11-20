<?php

declare(strict_types=1);

namespace App\Modules\Vacaciones\Services;

use App\Models\User;
use App\Modules\Vacaciones\Models\Vacacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VacacionService
{
    public function activar(User $user, string $fechaInicio, string $fechaFin, ?int $registradoPor): Vacacion
    {
        if (Carbon::parse($fechaFin)->lessThan(Carbon::parse($fechaInicio))) {
            throw ValidationException::withMessages([
                'fechaFin' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            ]);
        }

        return Vacacion::query()->create([
            'user_id' => $user->id,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'registrado_por' => $registradoPor,
        ]);
    }

    /**
     * @return Collection<int, Vacacion>
     */
    public function vigentes(): Collection
    {
        $hoy = Carbon::today();

        return Vacacion::query()
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin', '>=', $hoy)
            ->with('user')
            ->orderBy('fecha_fin')
            ->get();
    }

    /**
     * @return Collection<int, Vacacion>
     */
    public function historial(): Collection
    {
        return Vacacion::query()
            ->where('fecha_fin', '<', Carbon::today())
            ->with('user')
            ->latest('fecha_fin')
            ->get();
    }
}
