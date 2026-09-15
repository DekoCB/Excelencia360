<?php

declare(strict_types=1);

namespace App\Modules\Calendario\DTOs;

use App\Modules\Calendario\Enums\CategoriaCalendarioEnum;
use App\Modules\Calendario\Enums\TipoEventoEnum;
use Illuminate\Support\Carbon;

/**
 * Fila unificada del calendario, sin importar si proviene de un Horario
 * recurrente, una Evaluacion con fecha propia, o un EventoCalendario
 * puntual — ver CalendarioService::itemsDelMes().
 */
final readonly class ItemCalendarioData
{
    public function __construct(
        public Carbon $fecha,
        public CategoriaCalendarioEnum $categoria,
        public string $titulo,
        public ?string $subtitulo = null,
        public ?string $horaInicio = null,
        public ?string $horaFin = null,
        public ?TipoEventoEnum $tipoEvento = null,
        public ?int $eventoId = null,
    ) {}
}
