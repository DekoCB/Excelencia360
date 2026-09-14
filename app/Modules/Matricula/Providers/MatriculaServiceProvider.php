<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Providers;

use App\Modules\Matricula\Events\EstudianteMatriculado;
use App\Modules\Matricula\Listeners\GenerarDocumentosMatriculaListener;
use App\Modules\Matricula\Repositories\Contracts\EstudianteRepositoryInterface;
use App\Modules\Matricula\Repositories\Contracts\MatriculaRepositoryInterface;
use App\Modules\Matricula\Repositories\Eloquent\EloquentEstudianteRepository;
use App\Modules\Matricula\Repositories\Eloquent\EloquentMatriculaRepository;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class MatriculaServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EstudianteRepositoryInterface::class, EloquentEstudianteRepository::class);
        $this->app->bind(MatriculaRepositoryInterface::class, EloquentMatriculaRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');

        Event::listen(EstudianteMatriculado::class, GenerarDocumentosMatriculaListener::class);
    }
}
