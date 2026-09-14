<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Providers;

use App\Modules\Asistencia\Policies\HorarioAsistenciaPolicy;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class AsistenciaServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');

        Gate::define('asistencia.ver-horario', [HorarioAsistenciaPolicy::class, 'ver']);
        Gate::define('asistencia.registrar-horario', [HorarioAsistenciaPolicy::class, 'registrar']);
    }
}
