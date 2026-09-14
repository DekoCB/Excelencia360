<?php

use App\Http\Middleware\VerificarCuentaActiva;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        // Sin esto, Laravel solo escanea app/Console/Commands: los comandos
        // que viven dentro de cada módulo (p. ej.
        // Modules/Notificaciones/Console/Commands) nunca se registraban.
        // Ojo: apuntar el escaneo a app/Modules completo (en vez de listar
        // solo las carpetas Console/Commands de cada módulo) causó que en
        // Windows -- filesystem insensible a mayúsculas -- el detector de
        // comandos confundiera Routes/web.php con una clase, lo re-ejecutara
        // fuera del grupo "web" y pisara rutas ya registradas (p. ej.
        // /dashboard se quedaba sin el middleware VerificarCuentaActiva).
        __DIR__.'/../app/Console/Commands',
        ...glob(__DIR__.'/../app/Modules/*/Console/Commands', GLOB_ONLYDIR),
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            VerificarCuentaActiva::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
