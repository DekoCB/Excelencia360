<?php

declare(strict_types=1);

namespace App\Modules\Academico\Providers;

use App\Modules\Academico\Repositories\Contracts\CicloRepositoryInterface;
use App\Modules\Academico\Repositories\Contracts\CursoRepositoryInterface;
use App\Modules\Academico\Repositories\Contracts\HorarioRepositoryInterface;
use App\Modules\Academico\Repositories\Eloquent\EloquentCicloRepository;
use App\Modules\Academico\Repositories\Eloquent\EloquentCursoRepository;
use App\Modules\Academico\Repositories\Eloquent\EloquentHorarioRepository;
use App\Shared\Providers\ModuleServiceProvider;

class AcademicoServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CicloRepositoryInterface::class, EloquentCicloRepository::class);
        $this->app->bind(CursoRepositoryInterface::class, EloquentCursoRepository::class);
        $this->app->bind(HorarioRepositoryInterface::class, EloquentHorarioRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
