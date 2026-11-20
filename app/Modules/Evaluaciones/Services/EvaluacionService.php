<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Services;

use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Enums\RolEvaluacionEnum;
use App\Modules\Evaluaciones\Enums\TipoEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Notificaciones\Enums\TipoNotificacionEnum;
use App\Modules\Notificaciones\Services\NotificacionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class EvaluacionService
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
    ) {}

    /**
     * Estudiantes matriculados (aprobados) en el grado y ciclo de un
     * horario -- ver Matricula::scopeDelHorario().
     *
     * @return Collection<int, Estudiante>
     */
    public function estudiantesDelHorario(Horario $horario): Collection
    {
        return Estudiante::query()
            ->whereIn('id', Matricula::query()->delHorario($horario)->pluck('estudiante_id'))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();
    }

    /**
     * @return Collection<int, Horario>
     */
    public function horariosDelDocente(int $docenteId): Collection
    {
        return Horario::query()
            ->where('docente_id', $docenteId)
            ->whereNotNull('ciclo_id')
            ->with(['curso', 'grado', 'ciclo', 'docente', 'dias'])
            ->get();
    }

    /**
     * @return Collection<int, Horario>
     */
    public function horariosDelEstudiante(Estudiante $estudiante): Collection
    {
        $matriculas = $estudiante->matriculas()
            ->where('estado', 'aprobada')
            ->get(['id', 'grado_id', 'ciclo_id']);

        if ($matriculas->isEmpty()) {
            return new Collection;
        }

        return Horario::query()
            ->where(function ($query) use ($matriculas) {
                foreach ($matriculas as $matricula) {
                    $query->orWhere(fn ($query) => $query->deLaMatricula($matricula));
                }
            })
            ->with(['curso', 'grado', 'ciclo', 'docente', 'dias'])
            ->get();
    }

    /**
     * @return Collection<int, Horario>
     */
    public function todos(): Collection
    {
        // Un horario de curso corto (sin ciclo) no entra en Evaluaciones --
        // ver la decisión original de la modalidad Curso corto.
        return Horario::query()->whereNotNull('ciclo_id')->with(['curso', 'grado', 'ciclo', 'docente', 'dias'])->get();
    }

    /**
     * El enlace externo solo tiene sentido para una evaluación Física (la
     * Virtual se rinde dentro de la app, ver PreguntaService): se ignora
     * en vez de rechazarse si igual llega uno para no obligar al
     * formulario a condicionar el campo antes de enviar.
     *
     * $rol y $unidad ubican esta Evaluacion dentro de la fórmula de la
     * nota final (ver RolEvaluacionEnum): $unidad solo tiene sentido para
     * Criterio (1 o 2, varias por Unidad), y se ignora para cualquier otro
     * rol. Examen Final/Sustitutorio/Aplazado/Asistencia son únicos por
     * horario -- no se puede crear un segundo del mismo rol.
     */
    public function crear(CursoVirtual $curso, string $nombre, string $fecha, TipoEvaluacionEnum $tipo, RolEvaluacionEnum $rol, ?int $unidad = null, ?int $seccionId = null, ?string $enlaceExterno = null, ?string $disponibleHasta = null): Evaluacion
    {
        if ($rol === RolEvaluacionEnum::CRITERIO && ! in_array($unidad, [1, 2], true)) {
            throw ValidationException::withMessages([
                'unidad' => 'Un criterio debe pertenecer a la Unidad 1 o a la Unidad 2.',
            ]);
        }

        if ($rol->unicoPorHorario() && Evaluacion::query()->where('horario_id', $curso->horario_id)->where('rol', $rol)->exists()) {
            throw ValidationException::withMessages([
                'rol' => "Ya existe una evaluación de tipo «{$rol->label()}» para este horario.",
            ]);
        }

        return Evaluacion::query()->create([
            'horario_id' => $curso->horario_id,
            'curso_virtual_id' => $curso->id,
            'seccion_id' => $seccionId,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'rol' => $rol,
            'unidad' => $rol === RolEvaluacionEnum::CRITERIO ? $unidad : null,
            'fecha' => $fecha,
            'enlace_externo' => $tipo === TipoEvaluacionEnum::FISICO ? $enlaceExterno : null,
            'disponible_hasta' => $disponibleHasta,
            'estado' => EstadoEvaluacionEnum::BORRADOR,
        ]);
    }

    /**
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesDelCursoVirtual(CursoVirtual $curso): Collection
    {
        return Evaluacion::query()
            ->where('curso_virtual_id', $curso->id)
            ->orderByDesc('fecha')
            ->get();
    }

    public function actualizarEnlace(Evaluacion $evaluacion, ?string $enlaceExterno, ?string $disponibleHasta = null): Evaluacion
    {
        $evaluacion->update([
            'enlace_externo' => $enlaceExterno,
            'disponible_hasta' => $disponibleHasta,
        ]);

        return $evaluacion;
    }

    /**
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesDelHorario(Horario $horario): Collection
    {
        return Evaluacion::query()
            ->where('horario_id', $horario->id)
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesPublicadasDelHorario(Horario $horario): Collection
    {
        return Evaluacion::query()
            ->where('horario_id', $horario->id)
            ->where('estado', EstadoEvaluacionEnum::PUBLICADA)
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * @return Collection<int, Calificacion>
     */
    public function calificacionesDe(Evaluacion $evaluacion): Collection
    {
        return Calificacion::query()
            ->where('evaluacion_id', $evaluacion->id)
            ->with('estudiante')
            ->get()
            ->keyBy('estudiante_id');
    }

    public function calificar(Evaluacion $evaluacion, Estudiante $estudiante, float $nota, ?string $observaciones, ?int $registradoPor): Calificacion
    {
        return Calificacion::query()->updateOrCreate(
            ['evaluacion_id' => $evaluacion->id, 'estudiante_id' => $estudiante->id],
            ['nota_numerica' => $nota, 'observaciones' => $observaciones, 'registrado_por' => $registradoPor],
        );
    }

    /**
     * Importa notas desde las filas de un CSV/Excel -- típicamente
     * exportado de un Google Forms al que se le agregó una pregunta de
     * DNI, ya que el correo que exporta el Form por defecto no basta para
     * identificar sin ambigüedad al estudiante. Cada fila debe traer "dni"
     * y "nota" (0 a 20), y opcionalmente "observaciones". Solo califica a
     * estudiantes matriculados en el horario de la evaluación; cada fila
     * se procesa de forma independiente, así que una fila inválida no
     * afecta a las demás (mismo patrón que
     * MatriculaService::matricularDesdeFilas()).
     *
     * @param  SupportCollection<int, SupportCollection<string, mixed>>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function calificarDesdeFilas(Evaluacion $evaluacion, SupportCollection $filas, ?int $registradoPor): array
    {
        $exitosos = 0;
        $errores = [];

        $estudiantesPorDni = $this->estudiantesDelHorario($evaluacion->horario)->keyBy('dni');

        foreach ($filas as $indice => $fila) {
            try {
                $dni = trim((string) ($fila->get('dni') ?? ''));

                if ($dni === '') {
                    throw new InvalidArgumentException('La columna «dni» es obligatoria.');
                }

                $estudiante = $estudiantesPorDni->get($dni);

                if (! $estudiante) {
                    throw new InvalidArgumentException("No hay ningún estudiante matriculado en este horario con el DNI {$dni}.");
                }

                $notaValor = $fila->get('nota');

                if ($notaValor === null || trim((string) $notaValor) === '') {
                    throw new InvalidArgumentException('La columna «nota» es obligatoria.');
                }

                if (! is_numeric($notaValor)) {
                    throw new InvalidArgumentException("La nota «{$notaValor}» no es un número válido.");
                }

                $nota = (float) $notaValor;

                if ($nota < 0 || $nota > 20) {
                    throw new InvalidArgumentException('La nota debe estar entre 0 y 20.');
                }

                $observacionesValor = $fila->get('observaciones');
                $observaciones = $observacionesValor !== null && trim((string) $observacionesValor) !== ''
                    ? trim((string) $observacionesValor)
                    : null;

                $this->calificar($evaluacion, $estudiante, $nota, $observaciones, $registradoPor);

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $indice + 2, 'mensaje' => $e->getMessage()];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }

    public function publicar(Evaluacion $evaluacion): void
    {
        $evaluacion->update(['estado' => EstadoEvaluacionEnum::PUBLICADA]);

        $usuarios = $this->estudiantesDelHorario($evaluacion->horario)
            ->map(fn (Estudiante $estudiante) => $estudiante->user)
            ->filter();

        $this->notificaciones->notificarVarios(
            $usuarios,
            TipoNotificacionEnum::EVALUACION_PUBLICADA,
            "Se publicó la evaluación \"{$evaluacion->nombre}\"",
            route('aula-virtual.evaluacion', [$evaluacion->cursoVirtual, $evaluacion]),
        );
    }

    /**
     * Evaluaciones de un horario con un enlace externo que el estudiante ya
     * puede resolver: deben estar publicadas y, si el docente puso una
     * fecha límite, todavía no haber pasado (ver Evaluacion::enlaceDisponible()).
     *
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesConEnlaceDelHorario(Horario $horario): Collection
    {
        return Evaluacion::query()
            ->where('horario_id', $horario->id)
            ->where('estado', EstadoEvaluacionEnum::PUBLICADA)
            ->whereNotNull('enlace_externo')
            ->where(function ($query) {
                $query->whereNull('disponible_hasta')->orWhere('disponible_hasta', '>=', now());
            })
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * Calificaciones del estudiante en evaluaciones ya publicadas de un horario.
     *
     * @return Collection<int, Calificacion>
     */
    public function misCalificaciones(Estudiante $estudiante, Horario $horario): Collection
    {
        return Calificacion::query()
            ->where('estudiante_id', $estudiante->id)
            ->whereHas('evaluacion', function ($query) use ($horario) {
                $query->where('horario_id', $horario->id)->where('estado', EstadoEvaluacionEnum::PUBLICADA);
            })
            ->with('evaluacion')
            ->get();
    }

    /**
     * Promedio simple de los Criterios publicados y calificados de una
     * Unidad (1 o 2) del estudiante en un horario. Null si todavía no hay
     * ningún Criterio de esa Unidad calificado.
     */
    public function promedioUnidad(Estudiante $estudiante, Horario $horario, int $unidad): ?float
    {
        $notas = $this->misCalificaciones($estudiante, $horario)
            ->filter(fn (Calificacion $calificacion) => $calificacion->evaluacion->rol === RolEvaluacionEnum::CRITERIO && $calificacion->evaluacion->unidad === $unidad)
            ->pluck('nota_numerica');

        return $notas->isEmpty() ? null : round((float) $notas->avg(), 2);
    }

    /**
     * La nota publicada del estudiante en la (única) Evaluacion de este
     * rol para el horario -- Examen Final/Sustitutorio/Aplazado/
     * Asistencia. Null si no existe o todavía no se calificó.
     */
    private function notaPorRol(Estudiante $estudiante, Horario $horario, RolEvaluacionEnum $rol): ?float
    {
        $calificacion = $this->misCalificaciones($estudiante, $horario)
            ->first(fn (Calificacion $calificacion) => $calificacion->evaluacion->rol === $rol);

        return $calificacion !== null ? (float) $calificacion->nota_numerica : null;
    }

    /**
     * Promedio Final = 20% Unidad 1 + 20% Unidad 2 + 20% Asistencia + 40%
     * Examen Final. Null mientras falte alguno de los 4 componentes.
     * Aplazado, si existe, reemplaza directamente al Examen Final;
     * Sustitutorio, si existe, reemplaza la nota más baja entre
     * {Unidad 1, Unidad 2, Examen Final} (ya con el Aplazado aplicado).
     */
    public function promedioFinalDelEstudiante(Estudiante $estudiante, Horario $horario): ?float
    {
        $unidad1 = $this->promedioUnidad($estudiante, $horario, 1);
        $unidad2 = $this->promedioUnidad($estudiante, $horario, 2);
        $asistencia = $this->notaPorRol($estudiante, $horario, RolEvaluacionEnum::ASISTENCIA);
        $examenFinal = $this->notaPorRol($estudiante, $horario, RolEvaluacionEnum::APLAZADO)
            ?? $this->notaPorRol($estudiante, $horario, RolEvaluacionEnum::EXAMEN_FINAL);

        if ($unidad1 === null || $unidad2 === null || $asistencia === null || $examenFinal === null) {
            return null;
        }

        $sustitutorio = $this->notaPorRol($estudiante, $horario, RolEvaluacionEnum::SUSTITUTORIO);

        if ($sustitutorio !== null) {
            $masBaja = min($unidad1, $unidad2, $examenFinal);

            if ($unidad1 === $masBaja) {
                $unidad1 = $sustitutorio;
            } elseif ($unidad2 === $masBaja) {
                $unidad2 = $sustitutorio;
            } else {
                $examenFinal = $sustitutorio;
            }
        }

        return round(0.2 * $unidad1 + 0.2 * $unidad2 + 0.2 * $asistencia + 0.4 * $examenFinal, 2);
    }

    /**
     * El desglose por componente del promedio del estudiante en un
     * horario, para la libreta de notas: Unidad 1, Unidad 2, Asistencia y
     * Examen Final (null si todavía no se calificó cada uno), más
     * Sustitutorio/Aplazado solo si existen.
     *
     * @return array{unidad1: ?float, unidad2: ?float, asistencia: ?float, examenFinal: ?float, sustitutorio: ?float, aplazado: ?float}
     */
    public function desgloseDelEstudiante(Estudiante $estudiante, Horario $horario): array
    {
        return [
            'unidad1' => $this->promedioUnidad($estudiante, $horario, 1),
            'unidad2' => $this->promedioUnidad($estudiante, $horario, 2),
            'asistencia' => $this->notaPorRol($estudiante, $horario, RolEvaluacionEnum::ASISTENCIA),
            'examenFinal' => $this->notaPorRol($estudiante, $horario, RolEvaluacionEnum::EXAMEN_FINAL),
            'sustitutorio' => $this->notaPorRol($estudiante, $horario, RolEvaluacionEnum::SUSTITUTORIO),
            'aplazado' => $this->notaPorRol($estudiante, $horario, RolEvaluacionEnum::APLAZADO),
        ];
    }

    /**
     * Notas del estudiante en todos sus cursos matriculados (de cualquier
     * ciclo), usada por la sección "Mis evaluaciones" del dashboard — sin
     * tener que entrar horario por horario a revisar cada uno.
     *
     * Cada elemento es un array con las claves "horario" (Horario),
     * "calificaciones" (Collection<int, Calificacion>) y "promedio" (?float).
     * Sin generics en el @return: el TValue de Collection no es covariante
     * (ver https://phpstan.org/blog/whats-up-with-template-covariant), así
     * que ninguna anotación de forma de array sobrevive a un ->map().
     */
    public function resumenDelEstudiante(Estudiante $estudiante): SupportCollection
    {
        return collect($this->horariosDelEstudiante($estudiante))->map(fn (Horario $horario) => [
            'horario' => $horario,
            'calificaciones' => $this->misCalificaciones($estudiante, $horario),
            'promedio' => $this->promedioFinalDelEstudiante($estudiante, $horario),
        ]);
    }

    /**
     * El resumen anterior, agrupado por ciclo (más reciente primero) con el
     * promedio general de cada uno — usado por la sección "Mis evaluaciones"
     * del dashboard del estudiante.
     *
     * Cada elemento es un array con las claves "ciclo" (Ciclo), "cursos"
     * (la Collection que devuelve resumenDelEstudiante(), ya filtrada a ese
     * ciclo) y "promedioGeneral" (?float).
     */
    public function resumenDelEstudiantePorCiclo(Estudiante $estudiante): SupportCollection
    {
        return $this->resumenDelEstudiante($estudiante)
            ->groupBy(fn (array $item) => $item['horario']->ciclo->id)
            ->map(function ($cursos) {
                $promedios = $cursos->pluck('promedio')->filter();

                return [
                    'ciclo' => $cursos->first()['horario']->ciclo,
                    'cursos' => $cursos,
                    'promedioGeneral' => $promedios->isNotEmpty() ? round((float) $promedios->avg(), 2) : null,
                ];
            })
            ->sortByDesc(fn (array $grupo) => $grupo['ciclo']->id)
            ->values();
    }
}
