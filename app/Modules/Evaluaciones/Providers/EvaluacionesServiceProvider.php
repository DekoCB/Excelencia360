<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Providers;

use App\Modules\Evaluaciones\Policies\HorarioEvaluacionesPolicy;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class EvaluacionesServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');

        Gate::define('evaluaciones.ver-horario', [HorarioEvaluacionesPolicy::class, 'ver']);
        Gate::define('evaluaciones.registrar-horario', [HorarioEvaluacionesPolicy::class, 'registrar']);
    }
}
