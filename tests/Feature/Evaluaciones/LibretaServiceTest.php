<?php

namespace Tests\Feature\Evaluaciones;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Evaluaciones\Enums\TipoEvaluacionEnum;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LibretaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function libretaService(): LibretaService
    {
        return $this->app->make(LibretaService::class);
    }

    private function cursoVirtualDe(Horario $horario): CursoVirtual
    {
        return CursoVirtual::factory()->create(['horario_id' => $horario->id]);
    }

    public function test_generar_libreta_falla_si_no_hay_matricula_aprobada_en_ese_ciclo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $ciclo = Ciclo::factory()->create();

        $this->expectException(ValidationException::class);

        $this->libretaService()->generar($estudiante, $ciclo);
    }

    public function test_generar_libreta_calcula_el_promedio_por_curso_y_adjunta_el_pdf(): void
    {
        Storage::fake('public');

        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $evaluacionService = $this->app->make(EvaluacionService::class);
        $evaluacion = $evaluacionService->crear($this->cursoVirtualDe($horario), 'Evaluación', '2026-07-15', TipoEvaluacionEnum::FISICO);
        $evaluacionService->calificar($evaluacion, $estudiante, 16.0, null, null);
        $evaluacionService->publicar($evaluacion);

        $libreta = $this->libretaService()->generar($estudiante, $ciclo);

        $this->assertNotNull($libreta->generado_en);
        $this->assertNotNull($libreta->getFirstMedia('pdf'));
        $this->assertDatabaseHas('libretas', ['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);
    }

    public function test_generar_libreta_dos_veces_actualiza_la_misma_fila(): void
    {
        Storage::fake('public');

        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $this->libretaService()->generar($estudiante, $ciclo);
        $this->libretaService()->generar($estudiante, $ciclo);

        $this->assertDatabaseCount('libretas', 1);
    }

    public function test_resumen_por_cursos_devuelve_el_promedio_y_la_letra_por_curso(): void
    {
        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $evaluacionService = $this->app->make(EvaluacionService::class);
        $evaluacion = $evaluacionService->crear($this->cursoVirtualDe($horario), 'Evaluación', '2026-07-15', TipoEvaluacionEnum::FISICO);
        $evaluacionService->calificar($evaluacion, $estudiante, 18.0, null, null);
        $evaluacionService->publicar($evaluacion);

        $resumen = $this->libretaService()->resumenPorCursos($estudiante, $ciclo);

        $this->assertCount(1, $resumen);
        $this->assertSame($horario->curso->nombre, $resumen->first()['nombre']);
        $this->assertSame(18.0, $resumen->first()['promedio']);
        $this->assertSame('AD', $resumen->first()['letra']);
    }

    public function test_resumen_por_cursos_esta_vacio_sin_matricula_aprobada(): void
    {
        $estudiante = Estudiante::factory()->create();
        $ciclo = Ciclo::factory()->create();

        $resumen = $this->libretaService()->resumenPorCursos($estudiante, $ciclo);

        $this->assertTrue($resumen->isEmpty());
    }

    public function test_resumen_por_cursos_incluye_el_desglose_mes_a_mes(): void
    {
        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $evaluacionService = $this->app->make(EvaluacionService::class);
        $curso = $this->cursoVirtualDe($horario);

        $marzo = $evaluacionService->crear($curso, 'Evaluación de marzo', '2026-03-10', TipoEvaluacionEnum::FISICO);
        $evaluacionService->calificar($marzo, $estudiante, 14.0, null, null);
        $evaluacionService->publicar($marzo);

        $abril = $evaluacionService->crear($curso, 'Evaluación de abril', '2026-04-10', TipoEvaluacionEnum::FISICO);
        $evaluacionService->calificar($abril, $estudiante, 18.0, null, null);
        $evaluacionService->publicar($abril);

        $porMes = $this->libretaService()->resumenPorCursos($estudiante, $ciclo)->first()['porMes'];

        $this->assertCount(2, $porMes);
        $this->assertSame(14.0, $porMes[0]['promedio']);
        $this->assertSame(18.0, $porMes[1]['promedio']);
    }

    public function test_calcular_situacion_final_es_aprobado_sin_ningun_curso_con_letra_c(): void
    {
        $cursos = collect([
            ['letra' => 'AD'],
            ['letra' => 'A'],
            ['letra' => 'B'],
        ]);

        $this->assertSame('APROBADO', $this->libretaService()->calcularSituacionFinal($cursos));
    }

    public function test_calcular_situacion_final_es_desaprobado_si_algun_curso_tiene_letra_c(): void
    {
        $cursos = collect([
            ['letra' => 'AD'],
            ['letra' => 'C'],
        ]);

        $this->assertSame('DESAPROBADO', $this->libretaService()->calcularSituacionFinal($cursos));
    }

    public function test_calcular_situacion_final_no_cuenta_un_curso_sin_calificar_como_c(): void
    {
        $cursos = collect([
            ['letra' => 'AD'],
            ['letra' => null],
        ]);

        $this->assertSame('APROBADO', $this->libretaService()->calcularSituacionFinal($cursos));
    }

    public function test_periodo_promocional_es_anual_para_un_ciclo_anual(): void
    {
        $ciclo = Ciclo::factory()->anual()->create();

        $this->assertSame('ANUAL', $this->libretaService()->periodoPromocional($ciclo));
    }

    public function test_periodo_promocional_es_1_para_un_grupo_que_arranca_en_la_primera_mitad_del_anio(): void
    {
        // Ciclo::factory() por defecto es Grupo 1 (mesInicioFijo = 1).
        $ciclo = Ciclo::factory()->create(['anio' => 2026]);

        $this->assertSame('2026-1', $this->libretaService()->periodoPromocional($ciclo));
    }

    public function test_periodo_promocional_es_2_para_un_grupo_que_arranca_en_la_segunda_mitad_del_anio(): void
    {
        $ciclo = Ciclo::factory()->grupo3()->create(['anio' => 2026]);

        $this->assertSame('2026-2', $this->libretaService()->periodoPromocional($ciclo));
    }

    public function test_datos_para_pdf_incluye_todos_los_periodos_del_estudiante(): void
    {
        $ciclo = Ciclo::factory()->create(['anio' => 2026]);
        $estudiante = Estudiante::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $evaluacionService = $this->app->make(EvaluacionService::class);
        $evaluacion = $evaluacionService->crear($this->cursoVirtualDe($horario), 'Evaluación', '2026-07-15', TipoEvaluacionEnum::FISICO);
        $evaluacionService->calificar($evaluacion, $estudiante, 18.0, null, null);
        $evaluacionService->publicar($evaluacion);

        $datos = $this->libretaService()->datosParaPdf($estudiante);

        $this->assertSame($estudiante->id, $datos['estudiante']->id);
        $this->assertCount(1, $datos['periodos']);
        $this->assertSame($ciclo->id, $datos['periodos']->first()['ciclo']->id);
        $this->assertCount(1, $datos['periodos']->first()['cursos']);
    }

    public function test_datos_boleta_completa_trae_las_matriculas_en_orden_cronologico(): void
    {
        $estudiante = Estudiante::factory()->create();

        $cicloViejo = Ciclo::factory()->create(['anio' => 2025]);
        $horarioViejo = Horario::factory()->create(['ciclo_id' => $cicloViejo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $cicloViejo->id,
            'grado_id' => $horarioViejo->grado_id,
            'fecha_matricula' => '2025-01-10',
        ]);

        $cicloNuevo = Ciclo::factory()->create(['anio' => 2026]);
        $horarioNuevo = Horario::factory()->create(['ciclo_id' => $cicloNuevo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $cicloNuevo->id,
            'grado_id' => $horarioNuevo->grado_id,
            'fecha_matricula' => '2026-01-10',
        ]);

        $periodos = $this->libretaService()->datosBoletaCompleta($estudiante);

        $this->assertCount(2, $periodos);
        $this->assertSame($cicloViejo->id, $periodos->first()['ciclo']->id);
        $this->assertSame($cicloNuevo->id, $periodos->last()['ciclo']->id);
    }

    public function test_datos_boleta_completa_marca_vacio_un_periodo_sin_cursos(): void
    {
        $estudiante = Estudiante::factory()->create();
        $ciclo = Ciclo::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
        ]);

        $periodos = $this->libretaService()->datosBoletaCompleta($estudiante);

        $this->assertCount(1, $periodos);
        $this->assertTrue($periodos->first()['cursos']->isEmpty());
        $this->assertNull($periodos->first()['promedioPonderado']);
    }

    public function test_datos_boleta_completa_calcula_el_promedio_ponderado_por_creditos(): void
    {
        $estudiante = Estudiante::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
        ]);

        $cursoA = Curso::factory()->create(['creditos' => 4]);
        $horarioA = Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $matricula->grado_id, 'curso_id' => $cursoA->id]);
        $cursoB = Curso::factory()->create(['creditos' => 2]);
        $horarioB = Horario::factory()->create(['ciclo_id' => $ciclo->id, 'grado_id' => $matricula->grado_id, 'curso_id' => $cursoB->id]);

        $this->libretaService()->guardarNotaFinal($estudiante, $horarioA, 16.0, $horarioA->docente);
        $this->libretaService()->guardarNotaFinal($estudiante, $horarioB, 10.0, $horarioB->docente);

        $periodo = $this->libretaService()->datosBoletaCompleta($estudiante)->first();

        // (16*4 + 10*2) / 6 = 14.0
        $this->assertSame(14.0, $periodo['promedioPonderado']);
        $this->assertSame(6, $periodo['totalCreditos']);
        $this->assertSame(1, $periodo['cursosAprobados']);
    }

    public function test_guardar_nota_final_gana_sobre_el_promedio_calculado(): void
    {
        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $evaluacionService = $this->app->make(EvaluacionService::class);
        $evaluacion = $evaluacionService->crear($this->cursoVirtualDe($horario), 'Evaluación', '2026-07-15', TipoEvaluacionEnum::FISICO);
        $evaluacionService->calificar($evaluacion, $estudiante, 10.0, null, null);
        $evaluacionService->publicar($evaluacion);

        $this->libretaService()->guardarNotaFinal($estudiante, $horario, 17.5, $horario->docente);

        $resumen = $this->libretaService()->resumenPorCursos($estudiante, $ciclo);

        $this->assertSame(17.5, $resumen->first()['promedio']);
        $this->assertTrue($resumen->first()['editadaManualmente']);
        $this->assertSame('APROBADO', $resumen->first()['estado']);
    }
}
