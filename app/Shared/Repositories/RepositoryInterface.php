<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Contrato mínimo compartido por todos los repositorios de módulo.
 * Cada módulo extiende esta interfaz con sus propias consultas específicas
 * (p. ej. EstudianteRepositoryInterface::conDeudaVencida()).
 *
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /** @return TModel|null */
    public function find(int $id): ?Model;

    /** @return TModel */
    public function findOrFail(int $id): Model;

    /** @return Collection<int, TModel> */
    public function all(): Collection;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $atributos
     * @return TModel
     */
    public function create(array $atributos): Model;

    /**
     * @param  array<string, mixed>  $atributos
     * @return TModel
     */
    public function update(Model $modelo, array $atributos): Model;

    public function delete(Model $modelo): bool;
}
