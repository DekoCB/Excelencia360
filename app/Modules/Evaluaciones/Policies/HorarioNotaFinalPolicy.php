<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Policies;

use App\Models\User;
use App\Modules\Academico\Models\Horario;

/**
 * Autorización sobre "¿puede este usuario editar a mano la nota final de
 * este horario?" -- mismo criterio que
 * Asistencia\Policies\HorarioAsistenciaPolicy::registrar(): Dirección y
 * Coordinador en cualquier horario, o el docente dueño del horario.
 */
class HorarioNotaFinalPolicy
{
    public function editar(User $user, Horario $horario): bool
    {
        if ($user->hasRole('direccion') || $user->hasRole('coordinador')) {
            return true;
        }

        return $user->hasPermissionTo('evaluaciones.registrar') && $horario->docente_id === $user->id;
    }
}
