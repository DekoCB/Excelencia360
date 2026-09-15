<?php

declare(strict_types=1);

namespace App\Modules\Busqueda\DTOs;

/**
 * Una fila del buscador global, sin importar de qué módulo salió --
 * ver BusquedaGlobalService::buscar().
 */
final readonly class ResultadoBusquedaData
{
    public function __construct(
        public string $tipo,
        public string $titulo,
        public string $subtitulo,
        public string $url,
        public string $icono,
    ) {}
}
