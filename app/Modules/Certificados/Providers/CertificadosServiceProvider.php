<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Providers;

use App\Modules\Certificados\Models\Certificado;
use App\Modules\Certificados\Policies\CertificadoPolicy;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class CertificadosServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');

        Gate::policy(Certificado::class, CertificadoPolicy::class);
    }
}
