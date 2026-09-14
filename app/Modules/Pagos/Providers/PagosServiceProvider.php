<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Providers;

use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Policies\PagoPolicy;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class PagosServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');

        Gate::policy(Pago::class, PagoPolicy::class);
    }
}
