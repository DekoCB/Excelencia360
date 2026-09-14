<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Services;

use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Enums\NotaLetraEnum;
use App\Modules\Evaluaciones\Models\Libreta;
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

    public function __construct(
        private readonly EvaluacionService $evaluaciones,
    ) {}

    /**
     * El promedio por curso del estudiante en el ciclo, para mostrar en la
     * página de la libreta (y para armar el PDF). Devuelve una colección
     * vacía si no tiene una matrícula aprobada en ese ciclo, en vez de
     * lanzar una excepción: a diferencia de generar(), esto solo se usa
     * para mostrar contenido, no para disparar una acción.
     *
     * Cada elemento es un array con las claves "nombre" (string), "promedio"
     * (?float), "letra" (?string) y "porMes" (el desglose mes a mes, ver
     * EvaluacionService::promedioMensualDelEstudiante()). Sin generics en el
     * tipo de retorno: el TValue de Collection no es covariante (ver
     * https://phpstan.org/blog/whats-up-with-template-covariant), así que
     * ninguna anotación de forma de array sobrevive a un ->map().
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

        $horarios = Horario::query()
            ->deLaMatricula($matricula)
            ->with(['curso', 'ciclo'])
            ->get();

        return $horarios->map(function (Horario $horario) use ($estudiante) {
            $promedio = $this->evaluaciones->promedioDelEstudiante($estudiante, $horario);

            return [
                'nombre' => $horario->curso->nombre,
                'promedio' => $promedio,
                'letra' => $promedio !== null ? NotaLetraEnum::desde($promedio)->value : null,
                'porMes' => $this->evaluaciones->promedioMensualDelEstudiante($estudiante, $horario),
            ];
        });
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
     * o segunda mitad del año calendario), o "ANUAL" para SIAGIE anual --
     * a diferencia de Ciclo::nombre (texto libre tipo "Grupo 1 (Enero -
     * Junio)"), esto es el formato exigido en la libreta oficial.
     */
    public function periodoPromocional(Ciclo $ciclo): string
    {
        if ($ciclo->siagie?->tipo === TipoSiagieEnum::ANUAL) {
            return 'ANUAL';
        }

        $semestre = ($ciclo->tipo?->mesInicioFijo() ?? 1) > 6 ? 2 : 1;

        return "{$ciclo->anio}-{$semestre}";
    }

    /**
     * Todo lo que necesita la plantilla pdf.libreta -- separado de
     * generar() para que exportarLibretaPdf() en historial-estudiante (que
     * exporta sin persistir un registro Libreta) arme exactamente los
     * mismos datos, sin duplicar el cálculo de situación final/periodo.
     *
     * @return array{estudiante: Estudiante, ciclo: Ciclo, matricula: ?Matricula, cursos: SupportCollection, situacionFinal: string, periodoPromocional: string, modalidadTexto: string}
     */
    public function datosParaPdf(Estudiante $estudiante, Ciclo $ciclo): array
    {
        $matricula = Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('ciclo_id', $ciclo->id)
            ->where('estado', 'aprobada')
            ->with('grado')
            ->first();

        $cursos = $this->resumenPorCursos($estudiante, $ciclo);

        return [
            'estudiante' => $estudiante,
            'ciclo' => $ciclo,
            'matricula' => $matricula,
            'cursos' => $cursos,
            'situacionFinal' => $this->calcularSituacionFinal($cursos),
            'periodoPromocional' => $this->periodoPromocional($ciclo),
            'modalidadTexto' => self::MODALIDAD_EBA,
        ];
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

        $pdf = Pdf::loadView('pdf.libreta', $this->datosParaPdf($estudiante, $ciclo));

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
