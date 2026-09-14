<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class ReportesServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
