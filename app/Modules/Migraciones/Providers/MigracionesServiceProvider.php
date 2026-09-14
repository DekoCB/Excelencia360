<?php

declare(strict_types=1);

namespace App\Modules\Migraciones\Providers;

use App\Shared\Providers\ModuleServiceProvider;

class MigracionesServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
