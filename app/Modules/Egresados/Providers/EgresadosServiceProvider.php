<?php

declare(strict_types=1);

namespace App\Modules\Egresados\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class EgresadosServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
