<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Providers;

use App\Modules\Notificaciones\Contracts\WhatsAppGateway;
use App\Modules\Notificaciones\Gateways\MetaWhatsAppGateway;
use App\Modules\Notificaciones\Gateways\NullWhatsAppGateway;
use App\Shared\Providers\ModuleServiceProvider;

class NotificacionesServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsAppGateway::class, function () {
            if (config('services.whatsapp.driver') === 'meta') {
                return new MetaWhatsAppGateway(
                    (string) config('services.whatsapp.token'),
                    (string) config('services.whatsapp.phone_id'),
                );
            }

            return new NullWhatsAppGateway;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadWebRoutesFrom(__DIR__.'/../Routes/web.php');

        // El webhook lo llama Meta, no un navegador: sin grupo "web" (sin
        // sesión, sin CSRF), a diferencia de loadWebRoutesFrom().
        $this->loadRoutesFrom(__DIR__.'/../Routes/webhook.php');
    }
}
