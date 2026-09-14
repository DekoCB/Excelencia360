<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Notificaciones\Models\Notificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EvaluacionService
    {
        return $this->app->make(EvaluacionService::class);
    }

    public function test_crear_una_evaluacion_queda_en_estado_borrador(): void
    {
        $horario = Horario::factory()->create();

        $evaluacion = $this->service()->crear($horario, 'Evaluación mensual — julio', '2026-07-15');

        $this->assertSame(EstadoEvaluacionEnum::BORRADOR, $evaluacion->estado);
        $this->assertDatabaseHas('evaluaciones', ['nombre' => 'Evaluación mensual — julio', 'estado' => 'borrador']);
    }

    public function test_crear_una_evaluacion_persiste_la_semana_indicada(): void
    {
        $horario = Horario::factory()->create();

        $evaluacion = $this->service()->crear($horario, 'Evaluación mensual — julio', '2026-07-15', null, null, 4);

        $this->assertSame(4, $evaluacion->fresh()->semana);
    }

    public function test_actualizar_semana_cambia_la_semana_de_una_evaluacion(): void
    {
        $horario = Horario::factory()->create();
        $evaluacion = $this->service()->crear($horario, 'Evaluación mensual — julio', '2026-07-15', null, null, 1);

        $this->service()->actualizarSemana($evaluacion, 5);

        $this->assertSame(5, $evaluacion->fresh()->semana);
    }

    public function test_actualizar_semana_a_null_la_deja_en_bienvenida(): void
    {
        $horario = Horario::factory()->create();
        $evaluacion = $this->service()->crear($horario, 'Evaluación mensual — julio', '2026-07-15', null, null, 3);

        $this->service()->actualizarSemana($evaluacion, null);

        $this->assertNull($evaluacion->fresh()->semana);
    }

    public function test_estudiantes_del_horario_solo_incluye_matriculados_aprobados(): void
    {
        $horario = Horario::factory()->create();

        $matriculado = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $matriculado->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $noMatriculado = Estudiante::factory()->create();

        $estudiantes = $this->service()->estudiantesDelHorario($horario);

        $this->assertTrue($estudiantes->contains('id', $matriculado->id));
        $this->assertFalse($estudiantes->contains('id', $noMatriculado->id));
    }

    public function test_estudiantes_del_horario_incluye_a_todos_los_matriculados_del_mismo_grado_y_ciclo(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $horarioA = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $otroGrado = Grado::factory()->create();
        Horario::factory()->create(['grado_id' => $otroGrado->id, 'ciclo_id' => $ciclo->id]);

        $estudianteDelGrado = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudianteDelGrado->id,
            'grado_id' => $grado->id,
            'ciclo_id' => $ciclo->id,
        ]);

        $estudianteDeOtroGrado = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudianteDeOtroGrado->id,
            'grado_id' => $otroGrado->id,
            'ciclo_id' => $ciclo->id,
        ]);

        $estudiantesDeA = $this->service()->estudiantesDelHorario($horarioA);

        $this->assertTrue($estudiantesDeA->contains('id', $estudianteDelGrado->id));
        $this->assertFalse($estudiantesDeA->contains('id', $estudianteDeOtroGrado->id));
    }

    public function test_estudiantes_del_horario_incluye_matriculas_sin_horario_asignado_explicitamente(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $horarioA = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $estudianteSinHorario = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudianteSinHorario->id,
            'grado_id' => $grado->id,
            'ciclo_id' => $ciclo->id,
        ]);

        $estudiantesDeA = $this->service()->estudiantesDelHorario($horarioA);

        $this->assertTrue($estudiantesDeA->contains('id', $estudianteSinHorario->id));
    }

    public function test_horarios_del_estudiante_incluye_todos_los_horarios_de_su_grado_y_ciclo(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $horarioComunicacion = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $horarioMatematica = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $otroGrado = Grado::factory()->create();
        $horarioDeOtroGrado = Horario::factory()->create(['grado_id' => $otroGrado->id, 'ciclo_id' => $ciclo->id]);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $grado->id,
            'ciclo_id' => $ciclo->id,
        ]);

        $horarios = $this->service()->horariosDelEstudiante($estudiante);

        $this->assertTrue($horarios->contains('id', $horarioComunicacion->id));
        $this->assertTrue($horarios->contains('id', $horarioMatematica->id));
        $this->assertFalse($horarios->contains('id', $horarioDeOtroGrado->id));
    }

    public function test_calificar_dos_veces_al_mismo_estudiante_actualiza_en_vez_de_duplicar(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');

        $service->calificar($evaluacion, $estudiante, 15.5, 'Primer intento', null);
        $service->calificar($evaluacion, $estudiante, 18.0, 'Segundo intento', null);

        $this->assertDatabaseCount('calificaciones', 1);
        $this->assertDatabaseHas('calificaciones', ['nota_numerica' => 18.0, 'observaciones' => 'Segundo intento']);
    }

    public function test_un_estudiante_no_ve_calificaciones_de_una_evaluacion_en_borrador(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');
        $service->calificar($evaluacion, $estudiante, 17.0, null, null);

        $this->assertTrue($service->misCalificaciones($estudiante, $horario)->isEmpty());

        $service->publicar($evaluacion);

        $this->assertCount(1, $service->misCalificaciones($estudiante, $horario));
    }

    public function test_promedio_del_estudiante_solo_considera_evaluaciones_publicadas(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();

        $evaluacionUno = $service->crear($horario, 'Evaluación 1', '2026-07-01');
        $service->calificar($evaluacionUno, $estudiante, 14.0, null, null);
        $service->publicar($evaluacionUno);

        $evaluacionDos = $service->crear($horario, 'Evaluación 2', '2026-07-15');
        $service->calificar($evaluacionDos, $estudiante, 20.0, null, null);
        $service->publicar($evaluacionDos);

        $evaluacionSinPublicar = $service->crear($horario, 'Evaluación 3', '2026-07-20');
        $service->calificar($evaluacionSinPublicar, $estudiante, 0.0, null, null);

        $this->assertSame(17.0, $service->promedioDelEstudiante($estudiante, $horario));
    }

    public function test_promedio_del_estudiante_solo_considera_los_ultimos_6_examenes_en_un_ciclo_de_seis_meses(): void
    {
        // Ciclo::factory() no fija modalidad, así que hereda el default de
        // columna ('seis_meses') -- confirma que examenesQueCuentan() = 6.
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();

        // La primera evaluación (nota 0) queda fuera del promedio: hay 7
        // publicadas y solo cuentan las últimas 6 por fecha.
        $evaluacionMasAntigua = $service->crear($horario, 'Evaluación 1', '2026-01-10');
        $service->calificar($evaluacionMasAntigua, $estudiante, 0.0, null, null);
        $service->publicar($evaluacionMasAntigua);

        foreach (range(2, 7) as $numero) {
            $evaluacion = $service->crear($horario, "Evaluación {$numero}", "2026-0{$numero}-10");
            $service->calificar($evaluacion, $estudiante, 20.0, null, null);
            $service->publicar($evaluacion);
        }

        $this->assertSame(20.0, $service->promedioDelEstudiante($estudiante, $horario));
    }

    public function test_promedio_del_estudiante_considera_los_ultimos_8_examenes_en_un_ciclo_anual(): void
    {
        $ciclo = Ciclo::factory()->anual()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();

        // 9 evaluaciones publicadas, la más antigua (nota 0) queda fuera:
        // solo cuentan las últimas 8 por fecha.
        $evaluacionMasAntigua = $service->crear($horario, 'Marzo', '2026-03-10');
        $service->calificar($evaluacionMasAntigua, $estudiante, 0.0, null, null);
        $service->publicar($evaluacionMasAntigua);

        $meses = ['04', '05', '06', '07', '08', '09', '10', '11'];
        foreach ($meses as $mes) {
            $evaluacion = $service->crear($horario, "Evaluación {$mes}", "2026-{$mes}-10");
            $service->calificar($evaluacion, $estudiante, 20.0, null, null);
            $service->publicar($evaluacion);
        }

        $this->assertSame(20.0, $service->promedioDelEstudiante($estudiante, $horario));
    }

    public function test_publicar_notifica_a_los_estudiantes_matriculados_con_usuario_vinculado(): void
    {
        $horario = Horario::factory()->create();
        $usuario = User::factory()->create();
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');

        $service->publicar($evaluacion);

        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $usuario->id,
            'tipo' => 'evaluacion_publicada',
        ]);
    }

    public function test_publicar_no_notifica_a_estudiantes_sin_usuario_vinculado(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create(['user_id' => null]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');

        $service->publicar($evaluacion);

        $this->assertSame(0, Notificacion::query()->count());
    }

    public function test_horarios_del_estudiante_sin_asignacion_explicita_incluye_todos_los_horarios_del_grado(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $horarioA = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $horarioB = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $grado->id,
            'ciclo_id' => $ciclo->id,
        ]);

        $horarios = $this->service()->horariosDelEstudiante($estudiante);

        $this->assertTrue($horarios->contains('id', $horarioA->id));
        $this->assertTrue($horarios->contains('id', $horarioB->id));
    }

    /**
     * Cuando el MISMO curso tiene dos secciones (dos Horario con igual
     * curso_id+grado_id+ciclo_id), cada estudiante debe aparecer solo en
     * la sección que se le asignó explícitamente, no en ambas.
     */
    public function test_estudiantes_del_horario_con_secciones_paralelas_solo_incluye_a_quien_fue_asignado_a_esa_seccion(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $curso = Curso::factory()->create(['grado_id' => $grado->id]);

        $seccionA = Horario::factory()->create(['curso_id' => $curso->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $seccionB = Horario::factory()->create(['curso_id' => $curso->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $estudianteA = Estudiante::factory()->create();
        $matriculaA = Matricula::factory()->create(['estudiante_id' => $estudianteA->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $matriculaA->horarios()->attach($seccionA->id);

        $estudianteB = Estudiante::factory()->create();
        $matriculaB = Matricula::factory()->create(['estudiante_id' => $estudianteB->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $matriculaB->horarios()->attach($seccionB->id);

        $rosterSeccionA = $this->service()->estudiantesDelHorario($seccionA);

        $this->assertTrue($rosterSeccionA->contains('id', $estudianteA->id));
        $this->assertFalse($rosterSeccionA->contains('id', $estudianteB->id));
    }

    public function test_promedio_es_null_sin_calificaciones_publicadas(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $this->assertNull($this->service()->promedioDelEstudiante($estudiante, $horario));
    }

    public function test_crear_una_evaluacion_con_enlace_lo_persiste(): void
    {
        $horario = Horario::factory()->create();

        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen');

        $this->assertSame('https://forms.test/examen', $evaluacion->enlace_externo);
        $this->assertTrue($evaluacion->tieneEnlaceExterno());
    }

    public function test_crear_una_evaluacion_sin_enlace_lo_deja_nulo(): void
    {
        $horario = Horario::factory()->create();

        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15');

        $this->assertNull($evaluacion->enlace_externo);
        $this->assertFalse($evaluacion->tieneEnlaceExterno());
    }

    public function test_actualizar_enlace_modifica_el_enlace_de_una_evaluacion_existente(): void
    {
        $horario = Horario::factory()->create();
        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15');

        $this->service()->actualizarEnlace($evaluacion, 'https://forms.test/nuevo');

        $this->assertSame('https://forms.test/nuevo', $evaluacion->refresh()->enlace_externo);
    }

    public function test_evaluaciones_con_enlace_del_horario_solo_incluye_las_que_tienen_enlace(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();

        $conEnlace = $service->crear($horario, 'Con enlace', '2026-07-15', 'https://forms.test/examen');
        $service->publicar($conEnlace);
        $sinEnlace = $service->crear($horario, 'Sin enlace', '2026-07-16');
        $service->publicar($sinEnlace);

        $resultado = $service->evaluacionesConEnlaceDelHorario($horario);

        $this->assertTrue($resultado->contains('id', $conEnlace->id));
        $this->assertFalse($resultado->contains('id', $sinEnlace->id));
    }

    public function test_evaluaciones_con_enlace_del_horario_excluye_evaluaciones_en_borrador(): void
    {
        $horario = Horario::factory()->create();
        $evaluacion = $this->service()->crear($horario, 'Con enlace', '2026-07-15', 'https://forms.test/examen');

        $this->assertSame(EstadoEvaluacionEnum::BORRADOR, $evaluacion->estado);
        $this->assertFalse($this->service()->evaluacionesConEnlaceDelHorario($horario)->contains('id', $evaluacion->id));
    }

    public function test_evaluaciones_con_enlace_del_horario_excluye_las_vencidas(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();

        $vigente = $service->crear($horario, 'Vigente', '2026-07-15', 'https://forms.test/vigente', now()->addDay()->format('Y-m-d H:i:s'));
        $service->publicar($vigente);

        $vencida = $service->crear($horario, 'Vencida', '2026-07-14', 'https://forms.test/vencida', now()->subDay()->format('Y-m-d H:i:s'));
        $service->publicar($vencida);

        $resultado = $service->evaluacionesConEnlaceDelHorario($horario);

        $this->assertTrue($resultado->contains('id', $vigente->id));
        $this->assertFalse($resultado->contains('id', $vencida->id));
    }

    public function test_crear_una_evaluacion_con_disponible_hasta_lo_persiste(): void
    {
        $horario = Horario::factory()->create();
        $fecha = now()->addWeek();

        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen', $fecha->format('Y-m-d H:i:s'));

        $this->assertSame($fecha->format('Y-m-d H:i'), $evaluacion->disponible_hasta->format('Y-m-d H:i'));
    }

    public function test_actualizar_enlace_tambien_actualiza_disponible_hasta(): void
    {
        $horario = Horario::factory()->create();
        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen');
        $fecha = now()->addWeek();

        $this->service()->actualizarEnlace($evaluacion, 'https://forms.test/nuevo', $fecha->format('Y-m-d H:i:s'));

        $evaluacion->refresh();
        $this->assertSame('https://forms.test/nuevo', $evaluacion->enlace_externo);
        $this->assertSame($fecha->format('Y-m-d H:i'), $evaluacion->disponible_hasta->format('Y-m-d H:i'));
    }

    public function test_enlace_disponible_es_falso_si_no_esta_publicada(): void
    {
        $horario = Horario::factory()->create();
        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen');

        $this->assertFalse($evaluacion->enlaceDisponible());
    }

    public function test_enlace_disponible_es_verdadero_publicada_sin_fecha_limite(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen');
        $service->publicar($evaluacion);

        $this->assertTrue($evaluacion->refresh()->enlaceDisponible());
    }

    public function test_enlace_disponible_es_falso_publicada_pero_vencida(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen', now()->subDay()->format('Y-m-d H:i:s'));
        $service->publicar($evaluacion);

        $this->assertFalse($evaluacion->refresh()->enlaceDisponible());
    }

    public function test_resumen_del_estudiante_incluye_promedio_y_calificaciones_por_curso(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación mensual', '2026-07-15');
        $service->publicar($evaluacion);
        $service->calificar($evaluacion, $estudiante, 17.5, 'Buen desempeño', null);

        $resumen = $service->resumenDelEstudiante($estudiante);

        $this->assertCount(1, $resumen);
        $this->assertSame($horario->id, $resumen->first()['horario']->id);
        $this->assertSame(17.5, $resumen->first()['promedio']);
        $this->assertCount(1, $resumen->first()['calificaciones']);
    }

    public function test_resumen_del_estudiante_no_incluye_cursos_ajenos(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        Horario::factory()->create();

        $resumen = $this->service()->resumenDelEstudiante($estudiante);

        $this->assertCount(1, $resumen);
    }

    public function test_resumen_del_estudiante_sin_notas_deja_el_promedio_nulo(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $resumen = $this->service()->resumenDelEstudiante($estudiante);

        $this->assertNull($resumen->first()['promedio']);
        $this->assertTrue($resumen->first()['calificaciones']->isEmpty());
    }

    public function test_resumen_por_ciclo_agrupa_los_cursos_de_cada_ciclo_por_separado(): void
    {
        $grado = Grado::factory()->create();
        $cicloAnterior = Ciclo::factory()->create();
        $cicloActual = Ciclo::factory()->create();

        $horarioAnterior = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $cicloAnterior->id]);
        $horarioActual = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $cicloActual->id]);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'grado_id' => $grado->id, 'ciclo_id' => $cicloAnterior->id]);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'grado_id' => $grado->id, 'ciclo_id' => $cicloActual->id]);

        $service = $this->service();
        $evaluacionAnterior = $service->crear($horarioAnterior, 'Evaluación', '2026-03-15');
        $service->publicar($evaluacionAnterior);
        $service->calificar($evaluacionAnterior, $estudiante, 12.0, null, null);

        $evaluacionActual = $service->crear($horarioActual, 'Evaluación', '2026-07-15');
        $service->publicar($evaluacionActual);
        $service->calificar($evaluacionActual, $estudiante, 18.0, null, null);

        $porCiclo = $service->resumenDelEstudiantePorCiclo($estudiante);

        $this->assertCount(2, $porCiclo);
        $this->assertSame($cicloActual->id, $porCiclo->first()['ciclo']->id);
        $this->assertSame(18.0, $porCiclo->first()['promedioGeneral']);
        $this->assertSame($cicloAnterior->id, $porCiclo->last()['ciclo']->id);
        $this->assertSame(12.0, $porCiclo->last()['promedioGeneral']);
    }
}
