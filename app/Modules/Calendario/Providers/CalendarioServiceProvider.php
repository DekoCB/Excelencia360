<?php

declare(strict_types=1);

namespace App\Modules\Calendario\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class CalendarioServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
