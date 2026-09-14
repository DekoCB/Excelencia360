<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Policies;

use App\Models\User;
use App\Modules\Certificados\Models\Certificado;

class CertificadoPolicy
{
    public function view(User $user, Certificado $certificado): bool
    {
        if ($user->hasPermissionTo('certificados.ver')) {
            return true;
        }

        if (! $user->hasPermissionTo('certificados.solicitar')) {
            return false;
        }

        return $user->estudiante?->id === $certificado->estudiante_id;
    }
}
