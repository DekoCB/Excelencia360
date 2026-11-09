<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Services;

use App\Models\User;
use App\Modules\Academico\Enums\ModalidadCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Enums\NotaLetraEnum;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Evaluaciones\Models\NotaFinalCurso;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

class LibretaService
{
    /**
     * Texto oficial de la modalidad EBA, igual para todos los estudiantes
     * y ciclos -- no depende del grado ni del tipo/modalidad del Ciclo.
     */
    private const MODALIDAD_EBA = 'Educación Básica Alternativa – Ciclo Avanzado';

    /**
     * Nota mínima aprobatoria (escala vigesimal) -- mismo corte que ya usa
     * NotaLetraEnum::C para "En inicio"/desaprobado.
     */
    private const NOTA_MINIMA_APROBATORIA = 11;

    public function __construct(
        private readonly EvaluacionService $evaluaciones,
    ) {}

    /**
     * El promedio por curso del estudiante en el ciclo, para mostrar en la
     * página de la libreta (y para armar el PDF). Devuelve una colección
     * vacía si no tiene una matrícula aprobada en ese ciclo, en vez de
     * lanzar una excepción: a diferencia de generar(), esto solo se usa
     * para mostrar contenido, no para disparar una acción.
     */
    public function resumenPorCursos(Estudiante $estudiante, Ciclo $ciclo): SupportCollection
    {
        $matricula = Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('ciclo_id', $ciclo->id)
            ->where('estado', 'aprobada')
            ->first();

        if (! $matricula) {
            return collect();
        }

        return $this->resumenPorCursosDeLaMatricula($estudiante, $matricula);
    }

    /**
     * Cada elemento es un array con las claves "horario" (Horario, para
     * poder editar su nota final), "nombre"/"codigo"/"creditos" (del
     * curso), "cnd" (siempre "M": matriculado), "promedio" (la nota final
     * -- la editada a mano en NotaFinalCurso gana sobre el promedio
     * calculado de las evaluaciones en cuanto existe), "editadaManualmente"
     * (bool), "letra" (?string), "estado" ("APROBADO"/"DESAPROBADO"/null
     * mientras no haya nota) y "porMes" (el desglose mes a mes, ver
     * EvaluacionService::promedioMensualDelEstudiante()). Sin generics en
     * el tipo de retorno: el TValue de Collection no es covariante (ver
     * https://phpstan.org/blog/whats-up-with-template-covariant), así que
     * ninguna anotación de forma de array sobrevive a un ->map().
     */
    private function resumenPorCursosDeLaMatricula(Estudiante $estudiante, Matricula $matricula): SupportCollection
    {
        $horarios = Horario::query()
            ->deLaMatricula($matricula)
            ->with(['curso', 'ciclo'])
            ->get();

        $notasFinales = NotaFinalCurso::query()
            ->where('estudiante_id', $estudiante->id)
            ->whereIn('horario_id', $horarios->pluck('id'))
            ->get()
            ->keyBy('horario_id');

        return $horarios->map(function (Horario $horario) use ($estudiante, $notasFinales) {
            $notaFinal = $notasFinales->get($horario->id);
            $promedio = $notaFinal !== null
                ? (float) $notaFinal->nota
                : $this->evaluaciones->promedioDelEstudiante($estudiante, $horario);

            return [
                'horario' => $horario,
                'nombre' => $horario->curso->nombre,
                'codigo' => $horario->curso->codigo,
                'creditos' => $horario->curso->creditos,
                'cnd' => 'M',
                'promedio' => $promedio,
                'editadaManualmente' => $notaFinal !== null,
                'letra' => $promedio !== null ? NotaLetraEnum::desde($promedio)->value : null,
                'estado' => $promedio !== null
                    ? ($promedio >= self::NOTA_MINIMA_APROBATORIA ? 'APROBADO' : 'DESAPROBADO')
                    : null,
                'porMes' => $this->evaluaciones->promedioMensualDelEstudiante($estudiante, $horario),
            ];
        });
    }

    /**
     * Guarda (o reemplaza) la nota final que el docente edita a mano para
     * un estudiante en un horario -- gana sobre el promedio calculado de
     * las evaluaciones en resumenPorCursos()/datosBoletaCompleta(). La
     * autorización ("¿puede $editor editar la nota de este horario?") la
     * resuelve el llamador vía Gate::allows('evaluaciones.editar-nota-final', $horario).
     */
    public function guardarNotaFinal(Estudiante $estudiante, Horario $horario, float $nota, User $editor): NotaFinalCurso
    {
        return NotaFinalCurso::query()->updateOrCreate(
            ['estudiante_id' => $estudiante->id, 'horario_id' => $horario->id],
            ['nota' => $nota, 'editado_por' => $editor->id],
        );
    }

    /**
     * DESAPROBADO si algún curso quedó con letra C ("En inicio"); un curso
     * todavía sin calificar (letra null) no cuenta como C -- no hay nota
     * final que reprobar todavía.
     *
     * @param  SupportCollection<int, array{letra: ?string}>  $cursos
     */
    public function calcularSituacionFinal(SupportCollection $cursos): string
    {
        $tieneC = $cursos->contains(fn (array $curso) => $curso['letra'] === NotaLetraEnum::C->value);

        return $tieneC ? 'DESAPROBADO' : 'APROBADO';
    }

    /**
     * El "periodo promocional" tal como lo pide SIAGIE: "{año}-1"/"{año}-2"
     * para los Grupos de 6 meses (según si el Grupo arranca en la primera
     * o segunda mitad del año calendario), o "ANUAL" para el periodo
     * académico anual -- a diferencia de Ciclo::nombre (texto libre tipo
     * "Grupo 1 (Enero - Junio)"), esto es el formato exigido en la
     * libreta oficial.
     */
    public function periodoPromocional(Ciclo $ciclo): string
    {
        if ($ciclo->modalidad === ModalidadCicloEnum::ANUAL) {
            return 'ANUAL';
        }

        $semestre = ($ciclo->tipo?->mesInicioFijo() ?? 1) > 6 ? 2 : 1;

        return "{$ciclo->anio}-{$semestre}";
    }

    /**
     * Todo el historial del estudiante (un bloque por cada matrícula
     * aprobada, en orden cronológico), para la boleta de notas completa.
     * Cada elemento es un array con las claves "matricula", "ciclo",
     * "grado", "programaEstudio", "periodoAcademicoRomano", "cursos" (ver
     * resumenPorCursosDeLaMatricula()), "totalCreditos" (solo cursos con
     * créditos y nota cargados), "cursosAprobados" y "promedioPonderado"
     * (?float, null si ningún curso tiene créditos+nota cargados).
     */
    public function datosBoletaCompleta(Estudiante $estudiante): SupportCollection
    {
        $matriculas = Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('estado', 'aprobada')
            ->with(['ciclo', 'grado.programaEstudio'])
            ->orderBy('fecha_matricula')
            ->get();

        return $matriculas->map(function (Matricula $matricula) use ($estudiante) {
            $cursos = $this->resumenPorCursosDeLaMatricula($estudiante, $matricula);

            $cursosConCreditos = $cursos->filter(
                fn (array $curso) => $curso['creditos'] !== null && $curso['promedio'] !== null,
            );
            $totalCreditos = $cursosConCreditos->sum('creditos');
            $sumaPonderada = $cursosConCreditos->sum(fn (array $curso) => $curso['creditos'] * $curso['promedio']);

            return [
                'matricula' => $matricula,
                'ciclo' => $matricula->ciclo,
                'grado' => $matricula->grado,
                'programaEstudio' => $matricula->grado->programaEstudio,
                'periodoAcademicoRomano' => $this->numeroRomano($matricula->grado->orden),
                'cursos' => $cursos,
                'totalCreditos' => $totalCreditos,
                'cursosAprobados' => $cursos->filter(fn (array $curso) => $curso['estado'] === 'APROBADO')->count(),
                'promedioPonderado' => $totalCreditos > 0 ? round($sumaPonderada / $totalCreditos, 2) : null,
            ];
        });
    }

    /**
     * Todo lo que necesita la plantilla pdf.libreta -- separado de
     * generar() para que exportarLibretaPdf() en historial-estudiante (que
     * exporta sin persistir un registro Libreta) arme exactamente los
     * mismos datos. La boleta siempre muestra el historial completo del
     * estudiante, no un ciclo a la vez.
     *
     * @return array{estudiante: Estudiante, periodos: SupportCollection, modalidadTexto: string}
     */
    public function datosParaPdf(Estudiante $estudiante): array
    {
        return [
            'estudiante' => $estudiante,
            'periodos' => $this->datosBoletaCompleta($estudiante),
            'modalidadTexto' => self::MODALIDAD_EBA,
        ];
    }

    /**
     * Número romano de un periodo académico (orden del Grado/Semestre),
     * suficiente para el rango de semestres que maneja el sistema.
     */
    private function numeroRomano(int $numero): string
    {
        $romanos = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        return $romanos[$numero - 1] ?? (string) $numero;
    }

    public function generar(Estudiante $estudiante, Ciclo $ciclo): Libreta
    {
        $matricula = Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('ciclo_id', $ciclo->id)
            ->where('estado', 'aprobada')
            ->first();

        if (! $matricula) {
            throw ValidationException::withMessages([
                'ciclo' => 'El estudiante no tiene una matrícula aprobada en ese ciclo.',
            ]);
        }

        $pdf = Pdf::loadView('pdf.libreta', $this->datosParaPdf($estudiante));

        /** @var Libreta $libreta */
        $libreta = Libreta::query()->updateOrCreate(
            ['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id],
            ['generado_en' => now()],
        );

        $libreta->addMediaFromString($pdf->output())
            ->usingFileName("libreta-{$estudiante->id}-{$ciclo->id}.pdf")
            ->toMediaCollection('pdf');

        return $libreta;
    }

    /**
     * @return Collection<int, Libreta>
     */
    public function misLibretas(Estudiante $estudiante): Collection
    {
        return Libreta::query()
            ->where('estudiante_id', $estudiante->id)
            ->whereNotNull('generado_en')
            ->with(['ciclo', 'entregadoPor'])
            ->latest('generado_en')
            ->get();
    }

    /**
     * Todas las libretas generadas, para el historial de documentos que
     * revisa el personal (misma pantalla que certificados y constancias).
     *
     * @return Collection<int, Libreta>
     */
    public function todas(): Collection
    {
        return Libreta::query()
            ->whereNotNull('generado_en')
            ->with(['estudiante', 'ciclo', 'entregadoPor'])
            ->latest('generado_en')
            ->get();
    }
}
