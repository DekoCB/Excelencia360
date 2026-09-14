<?php

declare(strict_types=1);

namespace App\Modules\Incidencias\Providers;

use App\Modules\Incidencias\Policies\EstudianteIncidenciasPolicy;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class IncidenciasServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');

        Gate::define('incidencias.reportar-estudiante', [EstudianteIncidenciasPolicy::class, 'reportar']);
        Gate::define('incidencias.ver-estudiante', [EstudianteIncidenciasPolicy::class, 'ver']);
    }
}
