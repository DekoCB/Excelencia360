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

    public function emailDisponible(string $email, ?int $exceptoUserId = null): bool
    {
        return $this->usuarios->emailDisponible($email, $exceptoUserId);
    }

    /**
     * Además de la ficha del docente, crea (o reutiliza, si el DNI ya
     * tiene cuenta) su acceso al sistema -- a diferencia de
     * MatriculaService::registrarEstudiante(), el correo es el real del
     * docente (quien sí recibe comunicaciones institucionales), no uno
     * autoasignado por DNI; la contraseña inicial sigue siendo el DNI.
     *
     * @param  array{nombres: string, apellidos: string, dni: Dni, email: string, celular: ?Telefono, especialidad: ?string, gradoAcademico: ?string, fechaIngreso: ?string}  $datos
     */
    public function registrar(array $datos): Docente
    {
        return DB::transaction(function () use ($datos) {
            $email = $datos['email'];

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
                    $email = $this->celdaObligatoria($fila, 'email');

                    if (! $this->dniDisponible($dniTexto)) {
                        throw new InvalidArgumentException("Ya existe una cuenta registrada con el DNI {$dniTexto}.");
                    }

                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new InvalidArgumentException("El correo «{$email}» no es válido.");
                    }

                    if (! $this->emailDisponible($email)) {
                        throw new InvalidArgumentException("Ya existe una cuenta registrada con el correo {$email}.");
                    }

                    $celularTexto = $this->celdaOpcional($fila, 'celular');
                    $fechaIngresoValor = $fila->get('fecha_ingreso');

                    $this->registrar([
                        'nombres' => $nombres,
                        'apellidos' => $apellidos,
                        'dni' => new Dni($dniTexto),
                        'email' => $email,
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
