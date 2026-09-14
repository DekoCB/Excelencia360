<?php

declare(strict_types=1);

namespace App\Modules\Docentes\Services;

use App\Models\User;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\DTOs\CrearUsuarioData;
use App\Modules\Identidad\Services\UserManagementService;
use App\Shared\Enums\RolEnum;
use App\Shared\Support\ImportaFilasDeExcel;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class DocenteService
{
    use ImportaFilasDeExcel;

    public function __construct(
        private readonly UserManagementService $usuarios,
    ) {}

    public function listar(?string $termino, int $perPage = 15): LengthAwarePaginator
    {
        return Docente::query()
            ->with('usuario')
            ->when($termino, fn ($query) => $query->whereHas(
                'usuario',
                fn ($q) => $q->where('name', 'like', "%{$termino}%")->orWhere('dni', 'like', "%{$termino}%")
            ))
            ->latest()
            ->paginate($perPage);
    }

    public function dniDisponible(string $dni, ?int $exceptoUserId = null): bool
    {
        return ! User::query()
            ->where('dni', $dni)
            ->when($exceptoUserId, fn ($query) => $query->where('id', '!=', $exceptoUserId))
            ->exists();
    }

    /**
     * Además de la ficha del docente, crea (o reutiliza, si el DNI ya
     * tiene cuenta) su acceso al sistema -- mismo criterio que
     * MatriculaService::registrarEstudiante(): correo institucional
     * {dni}@ceba.test y contraseña inicial igual al DNI.
     *
     * @param  array{nombres: string, apellidos: string, dni: Dni, celular: ?Telefono, especialidad: ?string, gradoAcademico: ?string, fechaIngreso: ?string}  $datos
     */
    public function registrar(array $datos): Docente
    {
        return DB::transaction(function () use ($datos) {
            $email = $datos['dni']->correoInstitucional();

            $usuario = User::query()->where('dni', $datos['dni']->valor())->first();

            if ($usuario) {
                if (! $usuario->hasRole(RolEnum::DOCENTE->value)) {
                    $usuario->assignRole(RolEnum::DOCENTE->value);
                }
            } else {
                $usuario = $this->usuarios->crear(new CrearUsuarioData(
                    name: "{$datos['nombres']} {$datos['apellidos']}",
                    email: $email,
                    dni: $datos['dni'],
                    phone: $datos['celular'],
                    password: $datos['dni']->valor(),
                    rol: RolEnum::DOCENTE,
                ));
            }

            return Docente::query()->create([
                'user_id' => $usuario->id,
                'especialidad' => $datos['especialidad'],
                'grado_academico' => $datos['gradoAcademico'],
                'fecha_ingreso' => $datos['fechaIngreso'],
            ]);
        });
    }

    /**
     * @param  array{especialidad: ?string, gradoAcademico: ?string, fechaIngreso: ?string}  $datos
     */
    public function actualizar(Docente $docente, array $datos): Docente
    {
        $docente->update([
            'especialidad' => $datos['especialidad'],
            'grado_academico' => $datos['gradoAcademico'],
            'fecha_ingreso' => $datos['fechaIngreso'],
        ]);

        return $docente->fresh();
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function registrarDesdeFilas(Collection $filas): array
    {
        $exitosos = 0;
        $errores = [];

        foreach ($filas as $indice => $fila) {
            try {
                DB::transaction(function () use ($fila): void {
                    $nombres = $this->celdaObligatoria($fila, 'nombres');
                    $apellidos = $this->celdaObligatoria($fila, 'apellidos');
                    $dniTexto = $this->celdaObligatoria($fila, 'dni');

                    if (! $this->dniDisponible($dniTexto)) {
                        throw new InvalidArgumentException("Ya existe una cuenta registrada con el DNI {$dniTexto}.");
                    }

                    $celularTexto = $this->celdaOpcional($fila, 'celular');
                    $fechaIngresoValor = $fila->get('fecha_ingreso');

                    $this->registrar([
                        'nombres' => $nombres,
                        'apellidos' => $apellidos,
                        'dni' => new Dni($dniTexto),
                        'celular' => $celularTexto !== null ? new Telefono($celularTexto) : null,
                        'especialidad' => $this->celdaOpcional($fila, 'especialidad'),
                        'gradoAcademico' => $this->celdaOpcional($fila, 'grado_academico'),
                        'fechaIngreso' => $fechaIngresoValor !== null && trim((string) $fechaIngresoValor) !== ''
                            ? $this->parsearFecha($fechaIngresoValor)
                            : null,
                    ]);
                });

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $indice + 2, 'mensaje' => $this->mensajeDeError($e)];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }
}
