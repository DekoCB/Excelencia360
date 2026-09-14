<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identidad\Services\SessionControlService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta el acceso en el momento en que se desactiva una cuenta, en vez de
 * esperar a que la sesión expire sola por SESSION_LIFETIME -- sin esto,
 * el chequeo de LoginForm::authenticate() es la única puerta que se
 * cierra, pero cualquier sesión ya abierta seguiría funcionando
 * indefinidamente (ver UserManagementService::actualizar(), que revoca
 * las demás sesiones de la BD pero no puede tocar la petición ya en
 * vuelo de esa misma sesión).
 *
 * SessionControlService se inyecta por constructor, no como parámetro
 * extra de handle(): Pipeline invoca los middlewares globales llamando
 * directo a handle($request, $next), sin pasar por el resolver de
 * dependencias del contenedor -- un tercer parámetro típado ahí revienta
 * con "Too few arguments" en cada request.
 */
class VerificarCuentaActiva
{
    public function __construct(
        private readonly SessionControlService $sesiones,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario && ! $usuario->estaActivo()) {
            $this->sesiones->finalizarIngreso($request->session()->getId());

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
