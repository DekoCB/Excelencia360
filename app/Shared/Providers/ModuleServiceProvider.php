<?php

declare(strict_types=1);

namespace App\Shared\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base para los Service Provider de cada módulo (app/Modules/*). Rutas
 * cargadas vía loadRoutesFrom() NO heredan automáticamente el grupo de
 * middleware "web" (sesión, CSRF, cookies) — eso solo ocurre para el
 * archivo declarado en bootstrap/app.php. Usar loadWebRoutesFrom() en vez
 * de loadRoutesFrom() evita que un módulo nuevo repita el bug donde
 * /dashboard nunca arrancaba sesión y quedaba en loop con /login.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    protected function loadWebRoutesFrom(string $path): void
    {
        Route::middleware('web')->group(fn () => $this->loadRoutesFrom($path));
    }
}
