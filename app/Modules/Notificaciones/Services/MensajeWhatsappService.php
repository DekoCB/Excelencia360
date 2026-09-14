<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Services;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MensajeWhatsappService
{
    /**
     * @param  array{tipo?: string, estado?: string, campania_id?: int}  $filtros
     */
    public function listar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        return MensajeWhatsapp::query()
            ->with(['estudiante', 'campania'])
            ->when($filtros['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($filtros['campania_id'] ?? null, fn ($query, $campaniaId) => $query->where('campania_id', $campaniaId))
            ->latest('id')
            ->paginate($porPagina);
    }

    /**
     * Los mensajes (campañas y recordatorios) dirigidos a este estudiante,
     * para la vista "Mis mensajes" — excluye los tipos "entrante" e
     * "incidencia", que no son avisos del colegio hacia el estudiante.
     */
    public function misMensajes(Estudiante $estudiante, int $porPagina = 15): LengthAwarePaginator
    {
        return MensajeWhatsapp::query()
            ->where('estudiante_id', $estudiante->id)
            ->whereIn('tipo', [TipoMensajeWhatsappEnum::CAMPANIA, TipoMensajeWhatsappEnum::RECORDATORIO])
            ->latest('id')
            ->paginate($porPagina);
    }
}
