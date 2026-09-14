<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Providers;

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Policies\CursoVirtualPolicy;
use App\Modules\AulaVirtual\Repositories\Contracts\CursoVirtualRepositoryInterface;
use App\Modules\AulaVirtual\Repositories\Eloquent\EloquentCursoVirtualRepository;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class AulaVirtualServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CursoVirtualRepositoryInterface::class, EloquentCursoVirtualRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');

        Gate::policy(CursoVirtual::class, CursoVirtualPolicy::class);
    }
}
