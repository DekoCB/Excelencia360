<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Providers;

use App\Modules\Identidad\Listeners\RegistrarIntentoFallidoListener;
use App\Modules\Identidad\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Modules\Identidad\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Identidad\Repositories\Eloquent\EloquentAuditLogRepository;
use App\Modules\Identidad\Repositories\Eloquent\EloquentUserRepository;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;

class IdentidadServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditLogRepositoryInterface::class, EloquentAuditLogRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');

        Event::listen(Failed::class, RegistrarIntentoFallidoListener::class);
    }
}
