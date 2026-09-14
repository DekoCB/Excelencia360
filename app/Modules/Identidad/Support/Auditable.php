<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Support;

use App\Modules\Identidad\Jobs\RegistrarAuditoriaJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Añade auditoría automática (created/updated/deleted) a cualquier modelo
 * de negocio sensible (Matricula, Pago, Calificacion, Certificado...).
 * La escritura del log se despacha a cola: nunca bloquea la request.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            self::despacharAuditoria($model, 'created', [], $model->getAttributes());
        });

        static::updated(function ($model): void {
            if ($model->wasChanged()) {
                self::despacharAuditoria($model, 'updated', $model->getOriginal(), $model->getChanges());
            }
        });

        static::deleted(function ($model): void {
            self::despacharAuditoria($model, 'deleted', $model->getAttributes(), []);
        });
    }

    /**
     * @param  array<string, mixed>  $valoresAnteriores
     * @param  array<string, mixed>  $valoresNuevos
     */
    private static function despacharAuditoria($model, string $evento, array $valoresAnteriores, array $valoresNuevos): void
    {
        RegistrarAuditoriaJob::dispatch(
            Auth::id(),
            $evento,
            $model->getMorphClass(),
            $model->getKey(),
            self::ocultarSensibles($model, $valoresAnteriores),
            self::ocultarSensibles($model, $valoresNuevos),
            Request::ip(),
            substr((string) Request::userAgent(), 0, 255),
        );
    }

    /**
     * Los atributos marcados como $hidden en el modelo (password, tokens,
     * secretos 2FA...) nunca deben quedar legibles en el log de auditoría.
     * Se reemplaza el valor por un marcador sin borrar la clave, para que
     * siga siendo posible detectar QUE cambiaron (p. ej. para construir un
     * historial de cambios de contraseña) sin exponer el valor real.
     *
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private static function ocultarSensibles($model, array $valores): array
    {
        foreach ($model->getHidden() as $campo) {
            if (array_key_exists($campo, $valores)) {
                $valores[$campo] = '[oculto]';
            }
        }

        return $valores;
    }
}
