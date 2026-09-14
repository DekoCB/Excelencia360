<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Implementación Eloquent por defecto de {@see RepositoryInterface}.
 * Los repositorios de módulo extienden esta clase, implementan
 * {@see self::query()} devolviendo el builder de su propio modelo (p. ej.
 * `return User::query();`) y añaden sus métodos de consulta específicos;
 * nunca se expone el Model ni el Builder fuera del repositorio hacia la
 * capa de Servicio.
 *
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @return Builder<TModel>
     */
    abstract protected function query(): Builder;

    /**
     * @return TModel|null
     */
    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * @return TModel
     */
    public function findOrFail(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    /**
     * @return Collection<int, TModel>
     */
    public function all(): Collection
    {
        return $this->query()->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return TModel
     */
    public function create(array $atributos): Model
    {
        return $this->query()->create($atributos);
    }

    /**
     * @param  TModel  $modelo
     * @param  array<string, mixed>  $atributos
     * @return TModel
     */
    public function update(Model $modelo, array $atributos): Model
    {
        $modelo->fill($atributos);
        $modelo->save();

        return $modelo;
    }

    /**
     * @param  TModel  $modelo
     */
    public function delete(Model $modelo): bool
    {
        return (bool) $modelo->delete();
    }
}
