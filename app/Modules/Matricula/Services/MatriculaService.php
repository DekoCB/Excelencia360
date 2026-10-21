<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Services;

use App\Models\User;
use App\Modules\Academico\Enums\EstadoPeriodoMatriculaEnum;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Enums\TipoCursoEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\ProgramaEstudio;
use App\Modules\Identidad\DTOs\CrearUsuarioData;
use App\Modules\Identidad\Services\UserManagementService;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Modules\Matricula\Enums\EstadoEstudianteEnum;
use App\Modules\Matricula\Enums\EstadoMatriculaCursoCortoEnum;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use App\Modules\Matricula\Events\EstudianteMatriculado;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\InstitucionProcedencia;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Models\MatriculaCursoCorto;
use App\Modules\Matricula\Repositories\Contracts\EstudianteRepositoryInterface;
use App\Modules\Matricula\Repositories\Contracts\MatriculaRepositoryInterface;
use App\Shared\Enums\RolEnum;
use App\Shared\Support\ImportaFilasDeExcel;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class MatriculaService
{
    use ImportaFilasDeExcel;

    public function __construct(
        private readonly EstudianteRepositoryInterface $estudiantes,
        private readonly MatriculaRepositoryInterface $matriculas,
        private readonly UserManagementService $usuarios,
    ) {}

    public function listarEstudiantes(
        ?string $termino,
        ?string $estado,
        int $perPage = 15,
        ?int $cicloId = null,
        ?int $gradoId = null,
        ?int $cursoId = null,
        ?int $docenteId = null,
        ?string $modalidad = null,
    ): LengthAwarePaginator {
        return $this->estudiantes->buscar($termino, $estado, $perPage, $cicloId, $gradoId, $cursoId, $docenteId, $modalidad);
    }

    public function dniDisponible(string $dni, ?int $exceptoId = null): bool
    {
        return ! $this->estudiantes->existeDni($dni, $exceptoId);
    }

    /**
     * Borrado reversible (soft delete): los estudiantes desaparecen de
     * listas y búsquedas, pero su fila y todo lo relacionado (matrículas,
     * pagos, certificados, notas...) se conservan intactos -- Estudiante
     * ya usa SoftDeletes, así que ->delete() aquí nunca dispara el
     * cascadeOnDelete real de la base de datos (eso solo pasa con
     * forceDelete(), que esta función no usa a propósito).
     *
     * @param  list<int>  $ids
     */
    public function eliminarEstudiantes(array $ids): int
    {
        return Estudiante::query()->whereIn('id', $ids)->delete();
    }

    /**
     * Corrige los datos personales del estudiante desde su ficha (botón de
     * lápiz). El DNI no se toca aquí a propósito: de él se derivan el
     * correo institucional y la cuenta de acceso (ver registrarEstudiante()),
     * así que corregirlo necesita un tratamiento aparte, no uno genérico de
     * "editar ficha".
     *
     * @param  array{nombres: string, apellidos: string, fechaNacimiento: ?string, estadoCivil: ?string, direccion: ?string, celular: ?string, email: ?string, estado: string}  $datos
     */
    public function actualizarDatosPersonales(Estudiante $estudiante, array $datos): Estudiante
    {
        $estudiante->update([
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'fecha_nacimiento' => $datos['fechaNacimiento'],
            'es_menor_edad' => self::esMenorDeEdad($datos['fechaNacimiento']),
            'estado_civil' => $datos['estadoCivil'],
            'direccion' => $datos['direccion'],
            'celular' => $datos['celular'],
            'email' => $datos['email'],
            'estado' => $datos['estado'],
        ]);

        return $estudiante->fresh();
    }

    /**
     * Sin fecha de nacimiento (dato ahora opcional -- ver
     * registrarEstudiantesDesdeFilas()) no hay forma de saber la edad: se
     * asume que no es menor, ya que en este sistema el apoderado no es
     * exclusivo de menores de edad.
     */
    public static function esMenorDeEdad(?string $fechaNacimiento): bool
    {
        if ($fechaNacimiento === null || trim($fechaNacimiento) === '') {
            return false;
        }

        return Carbon::parse($fechaNacimiento)->age < 18;
    }

    /**
     * Además de la ficha del estudiante, esto le crea (o reutiliza, si ya
     * existe una con el mismo DNI) su cuenta de acceso al sistema: correo
     * {dni}@excelencia360.com y contraseña inicial igual a su DNI -- el estudiante
     * puede cambiarla después desde su perfil. No hay ningún paso de
     * formulario para esto: se calcula siempre a partir del DNI.
     */
    public function registrarEstudiante(RegistrarEstudianteData $data): Estudiante
    {
        return DB::transaction(function () use ($data) {
            $email = $data->dni->correoInstitucional();

            $usuario = User::query()->where('dni', $data->dni->valor())->first();

            if ($usuario) {
                if (! $usuario->hasRole(RolEnum::ESTUDIANTE->value)) {
                    $usuario->assignRole(RolEnum::ESTUDIANTE->value);
                }
            } else {
                $usuario = $this->usuarios->crear(new CrearUsuarioData(
                    name: "{$data->nombres} {$data->apellidos}",
                    email: $email,
                    dni: $data->dni,
                    phone: $data->celular,
                    password: $data->dni->valor(),
                    rol: RolEnum::ESTUDIANTE,
                ));
            }

            return $this->estudiantes->create([
                'user_id' => $usuario->id,
                'nombres' => $data->nombres,
                'apellidos' => $data->apellidos,
                'dni' => $data->dni->valor(),
                'fecha_nacimiento' => $data->fechaNacimiento,
                'es_menor_edad' => self::esMenorDeEdad($data->fechaNacimiento),
                'estado_civil' => $data->estadoCivil,
                'direccion' => $data->direccion,
                'celular' => $data->celular?->numero(),
                'email' => $email,
                'observaciones' => $data->observaciones,
                'estado' => EstadoEstudianteEnum::ACTIVO,
            ]);
        });
    }

    /**
     * Además de la ficha del apoderado, esto le crea (o reutiliza, si ya
     * existe una cuenta con el mismo DNI) acceso al portal de Apoderados
     * -- mismo criterio que registrarEstudiante()/DocenteService::registrar():
     * correo {dni}@excelencia360.com y contraseña inicial igual al DNI. Una misma
     * persona con más de un hijo matriculado tiene una fila de Apoderado
     * por cada uno (estudiante_id es unique), pero las que compartan DNI
     * quedan vinculadas a la MISMA cuenta vía user_id -- así ve a todos
     * sus hijos desde un solo inicio de sesión.
     */
    public function registrarApoderado(Estudiante $estudiante, RegistrarApoderadoData $data): Apoderado
    {
        if (! $estudiante->es_menor_edad) {
            throw ValidationException::withMessages([
                'apoderado' => 'Solo los estudiantes menores de edad requieren datos de apoderado.',
            ]);
        }

        return DB::transaction(function () use ($estudiante, $data) {
            $usuario = $this->vincularAccesoApoderado($data->dni, $data->nombres, $data->celular);

            return $estudiante->apoderado()->updateOrCreate([], [
                'user_id' => $usuario->id,
                'nombres' => $data->nombres,
                'dni' => $data->dni->valor(),
                'celular' => $data->celular->numero(),
                'correo' => $data->correo,
                'direccion' => $data->direccion,
                'parentesco' => $data->parentesco,
            ]);
        });
    }

    public function listarApoderados(?string $termino, int $perPage = 15): LengthAwarePaginator
    {
        return Apoderado::query()
            ->with('estudiante')
            ->when($termino, fn ($query) => $query->where(function ($query) use ($termino) {
                $query->where('nombres', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%")
                    ->orWhereHas('estudiante', function ($query) use ($termino) {
                        $query->where('nombres', 'like', "%{$termino}%")
                            ->orWhere('apellidos', 'like', "%{$termino}%");
                    });
            }))
            ->orderBy('nombres')
            ->paginate($perPage);
    }

    /**
     * Estudiantes menores de edad que todavía no tienen un apoderado
     * registrado -- para el selector de "nuevo apoderado" (Apoderado::
     * estudiante_id es unique, así que uno ya asignado se edita, no se
     * vuelve a crear).
     *
     * @return Collection<int, Estudiante>
     */
    public function estudiantesSinApoderado(): Collection
    {
        return Estudiante::query()
            ->where('es_menor_edad', true)
            ->whereDoesntHave('apoderado')
            ->orderBy('nombres')
            ->get();
    }

    private function vincularAccesoApoderado(Dni $dni, string $nombres, Telefono $celular): User
    {
        $usuario = User::query()->where('dni', $dni->valor())->first();

        if ($usuario) {
            if (! $usuario->hasRole(RolEnum::APODERADO->value)) {
                $usuario->assignRole(RolEnum::APODERADO->value);
            }

            return $usuario;
        }

        return $this->usuarios->crear(new CrearUsuarioData(
            name: $nombres,
            email: $dni->correoInstitucional(),
            dni: $dni,
            phone: $celular,
            password: $dni->valor(),
            rol: RolEnum::APODERADO,
        ));
    }

    /**
     * @param  array{nombre_colegio: string, ubicacion: ?string, anio_egreso: ?int}  $datos
     */
    public function registrarInstitucionProcedencia(Estudiante $estudiante, array $datos): InstitucionProcedencia
    {
        return $estudiante->institucionProcedencia()->updateOrCreate([], $datos);
    }

    public function matricular(Estudiante $estudiante, RegistrarMatriculaData $data): Matricula
    {
        $ciclo = Ciclo::query()->findOrFail($data->cicloId);
        $grado = Grado::query()->findOrFail($data->gradoId);

        if ($ciclo->modalidad !== ModalidadCicloEnum::ANUAL) {
            $this->validarPeriodoDeMatriculaAbierto($ciclo);
        }

        if ($this->matriculas->existeParaEstudianteYCiclo($estudiante->id, $ciclo->id)) {
            throw ValidationException::withMessages([
                'ciclo' => 'Este estudiante ya tiene una matrícula registrada para este ciclo.',
            ]);
        }

        $fechaMatricula = $data->fechaMatricula !== null ? Carbon::parse($data->fechaMatricula) : now();
        $fechaFinEstudio = $ciclo->modalidad === ModalidadCicloEnum::ANUAL
            ? $ciclo->fecha_fin
            : $fechaMatricula->clone()->addMonths($estudiante->es_menor_edad ? 8 : 6);

        return DB::transaction(function () use ($estudiante, $ciclo, $grado, $data, $fechaMatricula, $fechaFinEstudio) {
            $matricula = $this->matriculas->create([
                'estudiante_id' => $estudiante->id,
                'ciclo_id' => $ciclo->id,
                'grado_id' => $grado->id,
                'fecha_matricula' => $fechaMatricula,
                'fecha_fin_estudio' => $fechaFinEstudio,
                'estado' => EstadoMatriculaEnum::APROBADA,
                'observaciones' => $data->observaciones,
                'registrado_por' => $data->registradoPor,
            ]);

            $estudiante->update(['grado_actual_id' => $grado->id]);

            foreach ($data->cursoIds ?? [] as $cursoId) {
                $this->asignarCursoEspecifico($matricula, (int) $cursoId);
            }

            event(new EstudianteMatriculado($matricula));

            return $matricula;
        });
    }

    /**
     * Variante de asignarHorarioDeCurso() que resuelve el horario a partir
     * del curso en vez de pedirlo explícito -- para cuando solo hay un
     * Horario para ese curso+grado+ciclo (el caso normal). Si hay varios
     * (paralelos), no hay forma de adivinar cuál: se le pide a quien
     * matricula que lo asigne a mano después desde la ficha.
     */
    private function asignarCursoEspecifico(Matricula $matricula, int $cursoId): void
    {
        $horarios = Horario::query()
            ->where('curso_id', $cursoId)
            ->where('grado_id', $matricula->grado_id)
            ->where('ciclo_id', $matricula->ciclo_id)
            ->get();

        if ($horarios->count() !== 1) {
            throw ValidationException::withMessages([
                'cursoIds' => $horarios->isEmpty()
                    ? 'Uno de los cursos elegidos no tiene horario en ese semestre y período.'
                    : 'Uno de los cursos elegidos tiene varias secciones: asígnalo a mano desde la ficha del estudiante.',
            ]);
        }

        $matricula->horarios()->attach($horarios->first()->id);
    }

    /**
     * Matrícula de "curso corto": un estudiante que solo lleva un curso de
     * capacitación individual, sin semestre ni programa de estudio -- ver
     * App\Modules\Matricula\Models\MatriculaCursoCorto para el porqué de
     * que sea un modelo aparte de matricular(). A diferencia de esa, no
     * calcula una fecha fin automática ni dispara EstudianteMatriculado:
     * el curso corto no tiene plan de pago (si hace falta cobrar algo, se
     * usa CargoAdicional, que ya cobra a nivel de Estudiante).
     *
     * @param  array{curso_id: int, fecha_inicio: string, observaciones: ?string, registrado_por: ?int}  $datos
     */
    public function matricularCursoCorto(Estudiante $estudiante, array $datos): MatriculaCursoCorto
    {
        $curso = Curso::query()->findOrFail($datos['curso_id']);

        if ($curso->tipo !== TipoCursoEnum::CAPACITACION) {
            throw ValidationException::withMessages([
                'curso_id' => 'El curso elegido no es un curso de capacitación.',
            ]);
        }

        return MatriculaCursoCorto::query()->create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => $curso->id,
            'fecha_inicio' => $datos['fecha_inicio'],
            'estado' => EstadoMatriculaCursoCortoEnum::ACTIVO,
            'observaciones' => $datos['observaciones'] ?? null,
            'registrado_por' => $datos['registrado_por'] ?? null,
        ]);
    }

    /**
     * Marca un curso corto como concluido o retirado desde la ficha del
     * estudiante -- concluido deja fecha_fin en hoy si no se indica una
     * propia; retirado no, ya que el estudiante no llegó a terminarlo.
     */
    public function finalizarCursoCorto(MatriculaCursoCorto $cursoCorto, EstadoMatriculaCursoCortoEnum $estado, ?string $fechaFin = null): MatriculaCursoCorto
    {
        $cursoCorto->update([
            'estado' => $estado,
            'fecha_fin' => $estado === EstadoMatriculaCursoCortoEnum::CONCLUIDO ? ($fechaFin ?? now()->format('Y-m-d')) : $fechaFin,
        ]);

        return $cursoCorto;
    }

    /**
     * Reasigna la fecha en que culmina el periodo de estudio de esta
     * matrícula, por ejemplo desde la ficha del estudiante cuando se
     * necesita ajustarla manualmente (p. ej. un grado doble). Es
     * independiente del ciclo/horario: no afecta a qué grupo ni horario
     * pertenece el estudiante.
     */
    public function reasignarFechaFinEstudio(Matricula $matricula, string $fecha): Matricula
    {
        $matricula->update(['fecha_fin_estudio' => $fecha]);

        return $matricula;
    }

    /**
     * Asigna a la matrícula el horario específico de UN curso -- por
     * ejemplo cuando ese curso tiene varias secciones/paralelos y hace
     * falta decidir a cuál pertenece el estudiante (ver
     * Matricula::scopeDelHorario()). Reemplaza cualquier asignación previa
     * para el MISMO curso -- un estudiante no puede estar en dos secciones
     * de un mismo curso a la vez -- pero no toca las asignaciones de los
     * demás cursos del estudiante.
     */
    public function asignarHorarioDeCurso(Matricula $matricula, int $cursoId, ?int $horarioId): Matricula
    {
        $previo = $matricula->horarios()->where('curso_id', $cursoId)->first();

        if ($previo) {
            $matricula->horarios()->detach($previo->id);
        }

        if ($horarioId === null) {
            return $matricula->fresh();
        }

        $horario = Horario::query()
            ->where('id', $horarioId)
            ->where('curso_id', $cursoId)
            ->where('grado_id', $matricula->grado_id)
            ->where('ciclo_id', $matricula->ciclo_id)
            ->first();

        if ($horario === null) {
            throw ValidationException::withMessages([
                'horario' => 'El horario seleccionado no pertenece a ese curso, grado y ciclo.',
            ]);
        }

        $matricula->horarios()->attach($horario->id);

        return $matricula->fresh();
    }

    /**
     * Solo aplica a los 4 Grupos rotativos de 6 meses, cada uno con su
     * propia ventana de admisión (fecha_inicio_matricula/fecha_fin_matricula
     * en el propio Ciclo) que un coordinador abre y cierra a mano. El
     * Periodo Académico anual no tiene este concepto -- se identifica solo
     * por año y su matrícula está disponible mientras el ciclo esté
     * vigente (ver el llamado condicional en matricular()).
     */
    private function validarPeriodoDeMatriculaAbierto(Ciclo $ciclo): void
    {
        $hoy = Carbon::today();

        $periodoAbierto = $ciclo->estado_matricula === EstadoPeriodoMatriculaEnum::ABIERTO
            && $ciclo->fecha_inicio_matricula !== null
            && $ciclo->fecha_fin_matricula !== null
            && $ciclo->fecha_inicio_matricula->lessThanOrEqualTo($hoy)
            && $ciclo->fecha_fin_matricula->greaterThanOrEqualTo($hoy);

        if (! $periodoAbierto) {
            throw ValidationException::withMessages([
                'ciclo' => "No hay un periodo de matrícula abierto hoy para el ciclo «{$ciclo->nombre}».",
            ]);
        }
    }

    /**
     * Crea estudiantes (y su apoderado si son menores de edad) a partir de
     * las filas de un Excel. fecha_nacimiento es opcional: sin ella,
     * es_menor_edad queda en false (ver esMenorDeEdad()) y no se exige
     * apoderado en esta fila. Cada fila se procesa en su propia transacción:
     * una fila inválida no afecta a las demás, y su error se reporta con el
     * número de fila tal como aparece en el archivo (encabezado = fila 1).
     *
     * @param  Collection<int, Collection<string, mixed>>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function registrarEstudiantesDesdeFilas(Collection $filas): array
    {
        $exitosos = 0;
        $errores = [];

        foreach ($filas as $indice => $fila) {
            try {
                DB::transaction(function () use ($fila): void {
                    $nombres = $this->celdaObligatoria($fila, 'nombres');
                    $apellidos = $this->celdaObligatoria($fila, 'apellidos');
                    $dniTexto = $this->celdaObligatoria($fila, 'dni');
                    $fechaNacimientoValor = $fila->get('fecha_nacimiento');
                    $fechaNacimientoTexto = $fechaNacimientoValor !== null && trim((string) $fechaNacimientoValor) !== ''
                        ? $this->parsearFecha($fechaNacimientoValor)
                        : null;

                    if (! $this->dniDisponible($dniTexto)) {
                        throw new InvalidArgumentException("Ya existe un estudiante registrado con el DNI {$dniTexto}.");
                    }

                    $estadoCivilTexto = $this->celdaOpcional($fila, 'estado_civil');
                    $estadoCivil = null;
                    if ($estadoCivilTexto !== null) {
                        $estadoCivil = EstadoCivilEnum::tryFrom(Str::lower($estadoCivilTexto));
                        if ($estadoCivil === null) {
                            throw new InvalidArgumentException("Estado civil «{$estadoCivilTexto}» no reconocido (usa: soltero, casado, conviviente, divorciado, viudo).");
                        }
                    }

                    $celularTexto = $this->celdaOpcional($fila, 'celular');

                    $estudiante = $this->registrarEstudiante(new RegistrarEstudianteData(
                        nombres: $nombres,
                        apellidos: $apellidos,
                        dni: new Dni($dniTexto),
                        fechaNacimiento: $fechaNacimientoTexto,
                        estadoCivil: $estadoCivil,
                        direccion: $this->celdaOpcional($fila, 'direccion'),
                        celular: $celularTexto !== null ? new Telefono($celularTexto) : null,
                        observaciones: $this->celdaOpcional($fila, 'observaciones'),
                    ));

                    if ($estudiante->es_menor_edad) {
                        $this->registrarApoderado($estudiante, new RegistrarApoderadoData(
                            nombres: $this->celdaObligatoria($fila, 'apoderado_nombres', 'El estudiante es menor de edad: el nombre del apoderado es obligatorio.'),
                            dni: new Dni($this->celdaObligatoria($fila, 'apoderado_dni', 'El estudiante es menor de edad: el DNI del apoderado es obligatorio.')),
                            celular: new Telefono($this->celdaObligatoria($fila, 'apoderado_celular', 'El estudiante es menor de edad: el celular del apoderado es obligatorio.')),
                            correo: $this->celdaOpcional($fila, 'apoderado_correo'),
                            direccion: $this->celdaOpcional($fila, 'apoderado_direccion'),
                            parentesco: $this->celdaObligatoria($fila, 'apoderado_parentesco', 'El estudiante es menor de edad: el parentesco del apoderado es obligatorio.'),
                        ));
                    }
                });

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $indice + 2, 'mensaje' => $this->mensajeDeError($e)];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }

    /**
     * Matricula estudiantes ya existentes (identificados por DNI) en un
     * mismo ciclo, cada uno en el grado indicado en su fila. Reutiliza
     * matricular(), así que cada fila respeta las mismas validaciones que
     * una matrícula individual (grado coherente con la edad, periodo de
     * matrícula abierto, sin duplicados).
     *
     * @param  Collection<int, Collection<string, mixed>>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    /**
     * Columnas: dni, carrera, semestre, curso (opcional), observaciones
     * (opcional). "carrera" desambigua semestres con el mismo nombre en
     * carreras distintas (ej. "Semestre 1" existe en varias); sin ella, el
     * semestre se buscaba solo por nombre y podía chocar con otro igual de
     * otra carrera. "curso" es opcional: si se llena, matricula solo en
     * ESE curso del semestre (ver RegistrarMatriculaData::$cursoIds); si
     * se deja vacío, matricula en todo el semestre como siempre.
     */
    public function matricularDesdeFilas(int $cicloId, Collection $filas, ?int $registradoPor): array
    {
        $exitosos = 0;
        $errores = [];

        foreach ($filas as $indice => $fila) {
            try {
                $dniTexto = (new Dni($this->celdaObligatoria($fila, 'dni')))->valor();
                $nombreCarrera = $this->celdaObligatoria($fila, 'carrera');
                $nombreGrado = $this->celdaObligatoria($fila, 'semestre');

                $estudiante = Estudiante::query()->where('dni', $dniTexto)->first();
                if (! $estudiante) {
                    throw new InvalidArgumentException("No existe ningún estudiante registrado con el DNI {$dniTexto}.");
                }

                $programaEstudio = ProgramaEstudio::query()->where('nombre', $nombreCarrera)->first();
                if (! $programaEstudio) {
                    throw new InvalidArgumentException("No existe la carrera «{$nombreCarrera}».");
                }

                $grado = Grado::query()->where('programa_estudio_id', $programaEstudio->id)->where('nombre', $nombreGrado)->first();
                if (! $grado) {
                    throw new InvalidArgumentException("La carrera «{$nombreCarrera}» no tiene el semestre «{$nombreGrado}».");
                }

                $cursoIds = null;
                $nombreCurso = $this->celdaOpcional($fila, 'curso');

                if ($nombreCurso !== null && trim($nombreCurso) !== '') {
                    $curso = Curso::query()->whereHas('grados', fn ($q) => $q->where('grados.id', $grado->id))->where('nombre', $nombreCurso)->first();
                    if (! $curso) {
                        throw new InvalidArgumentException("El semestre «{$nombreGrado}» no tiene el curso «{$nombreCurso}».");
                    }
                    $cursoIds = [$curso->id];
                }

                $this->matricular($estudiante, new RegistrarMatriculaData(
                    cicloId: $cicloId,
                    gradoId: $grado->id,
                    observaciones: $this->celdaOpcional($fila, 'observaciones'),
                    registradoPor: $registradoPor,
                    cursoIds: $cursoIds,
                ));

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $indice + 2, 'mensaje' => $this->mensajeDeError($e)];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }

    /**
     * Carga masiva de curso corto: columnas dni, curso. El estudiante ya
     * debe estar registrado (se busca por dni); el curso debe ser de
     * tipo=capacitacion, igual que matricularCursoCorto().
     *
     * @param  Collection<int, Collection<string, mixed>>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function matricularCursoCortoDesdeFilas(Collection $filas, ?int $registradoPor): array
    {
        $exitosos = 0;
        $errores = [];

        foreach ($filas as $indice => $fila) {
            try {
                $dniTexto = (new Dni($this->celdaObligatoria($fila, 'dni')))->valor();
                $nombreCurso = $this->celdaObligatoria($fila, 'curso');

                $estudiante = Estudiante::query()->where('dni', $dniTexto)->first();
                if (! $estudiante) {
                    throw new InvalidArgumentException("No existe ningún estudiante registrado con el DNI {$dniTexto}.");
                }

                $curso = Curso::query()->where('tipo', TipoCursoEnum::CAPACITACION)->where('nombre', $nombreCurso)->first();
                if (! $curso) {
                    throw new InvalidArgumentException("No existe el curso corto «{$nombreCurso}».");
                }

                $this->matricularCursoCorto($estudiante, [
                    'curso_id' => $curso->id,
                    'fecha_inicio' => now()->format('Y-m-d'),
                    'observaciones' => null,
                    'registrado_por' => $registradoPor,
                ]);

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $indice + 2, 'mensaje' => $this->mensajeDeError($e)];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }
}
