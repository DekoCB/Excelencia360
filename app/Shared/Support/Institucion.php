<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Acceso a la identidad institucional (config/institucion.php) desde
 * vistas, PDFs y servicios. Centraliza la comprobación del logo: el
 * archivo puede no existir todavía y cada vista mostraría el escudo roto
 * si lo enlazara a ciegas.
 */
final class Institucion
{
    public static function nombre(): string
    {
        return (string) config('institucion.nombre');
    }

    public static function nombreCorto(): string
    {
        return (string) config('institucion.nombre_corto');
    }

    public static function tieneLogo(): bool
    {
        return self::logoPath() !== null;
    }

    /**
     * Ruta absoluta del logo (para DomPDF), o null si aún no se ha subido.
     */
    public static function logoPath(): ?string
    {
        $relativo = config('institucion.logo');

        if (! is_string($relativo) || $relativo === '') {
            return null;
        }

        $absoluto = public_path($relativo);

        return is_file($absoluto) ? $absoluto : null;
    }

    /**
     * URL pública del logo, o null si aún no se ha subido.
     */
    public static function logoUrl(): ?string
    {
        return self::tieneLogo() ? asset((string) config('institucion.logo')) : null;
    }

    /**
     * URL del favicon: el logo si existe, si no el placeholder SVG.
     */
    public static function faviconUrl(): string
    {
        return self::logoUrl() ?? asset((string) config('institucion.favicon'));
    }

    /**
     * Dirección completa en una línea, para PDFs y el pie de la web.
     */
    public static function direccionCompleta(): string
    {
        return implode(', ', array_filter([
            config('institucion.direccion'),
            config('institucion.ciudad'),
            config('institucion.pais'),
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function cursos(): array
    {
        return (array) config('institucion.cursos', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function curso(string $slug): ?array
    {
        foreach (self::cursos() as $curso) {
            if (($curso['slug'] ?? null) === $slug) {
                return $curso;
            }
        }

        return null;
    }

    /**
     * Opciones válidas del campo "Asunto" del formulario de contacto:
     * cada curso, cada servicio y una consulta general.
     *
     * @return list<string>
     */
    public static function asuntosContacto(): array
    {
        $cursos = array_map(fn (array $curso) => 'Curso: '.$curso['nombre'], self::cursos());
        $servicios = array_map(fn (array $servicio) => 'Servicio: '.$servicio['nombre'], (array) config('institucion.servicios', []));

        return [...$cursos, ...$servicios, 'Consulta general'];
    }
}
