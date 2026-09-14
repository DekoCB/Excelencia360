<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Amplía /up (health: '/up' en bootstrap/app.php) más allá de "el proceso
 * PHP responde": valida que la app pueda de verdad hablar con BD, caché y
 * cola. Cualquier excepción lanzada aquí hace que /up devuelva 500 en vez
 * de 200 — ver Illuminate\Foundation\Configuration\ApplicationBuilder.
 */
class DiagnosticarSaludDelSistemaListener
{
    public function handle(DiagnosingHealth $event): void
    {
        $this->verificarBaseDeDatos();
        $this->verificarCache();
        $this->verificarCola();
    }

    private function verificarBaseDeDatos(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo conectar a la base de datos.', previous: $e);
        }
    }

    private function verificarCache(): void
    {
        $clave = 'healthcheck:'.now()->timestamp;

        try {
            Cache::put($clave, true, 5);
            $ok = Cache::pull($clave) === true;
        } catch (Throwable $e) {
            throw new RuntimeException('El almacén de caché no responde.', previous: $e);
        }

        if (! $ok) {
            throw new RuntimeException('El almacén de caché no devolvió el valor esperado.');
        }
    }

    private function verificarCola(): void
    {
        // Solo se puede verificar de forma directa con el driver 'database'
        // (el que usa este entorno). Con Redis/SQS en producción, Laravel
        // ya valida esa conexión al resolver el manager de colas.
        if (config('queue.default') !== 'database') {
            return;
        }

        try {
            DB::table('jobs')->count();
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo consultar la tabla de colas.', previous: $e);
        }
    }
}
