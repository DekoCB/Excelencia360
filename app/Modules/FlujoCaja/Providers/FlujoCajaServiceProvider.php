<?php

declare(strict_types=1);

namespace App\Modules\FlujoCaja\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class FlujoCajaServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
