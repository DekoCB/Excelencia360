<?php

declare(strict_types=1);

namespace App\Modules\Vacaciones\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class VacacionesServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
