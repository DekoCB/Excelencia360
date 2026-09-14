<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class DashboardServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
