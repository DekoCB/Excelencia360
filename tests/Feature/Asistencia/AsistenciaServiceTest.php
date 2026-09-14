<?php

namespace Tests\Feature\Asistencia;

use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsistenciaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AsistenciaService
    {
        return $this->app->make(AsistenciaService::class);
    }

    /**
     * @param  array<string, mixed>  $atributosHorario
     * @param  list<DiaSemanaEnum>  $dias
     */
    private function horarioConDias(array $atributosHorario, array $dias, string $horaInicio = '18:00:00', string $horaFin = '20:00:00'): Horario
    {
        $horario = Horario::factory()->create($atributosHorario);
        $horario->dias()->delete();

        foreach ($dias as $dia) {
            $horario->dias()->create([
                'dia_semana' => $dia,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
            ]);
        }

        return $horario->fresh(['dias']);
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

    public function test_estudiantes_del_horario_no_mezcla_otros_grados_o_ciclos(): void
    {
        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create();

        $estudianteA = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudianteA->id,
            'grado_id' => $horarioA->grado_id,
            'ciclo_id' => $horarioA->ciclo_id,
        ]);

        $estudianteB = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudianteB->id,
            'grado_id' => $horarioB->grado_id,
            'ciclo_id' => $horarioB->ciclo_id,
        ]);

        $estudiantesDeA = $this->service()->estudiantesDelHorario($horarioA);

        $this->assertTrue($estudiantesDeA->contains('id', $estudianteA->id));
        $this->assertFalse($estudiantesDeA->contains('id', $estudianteB->id));
    }

    /**
     * Cuando un grado tiene varios cursos (cada uno con un solo horario,
     * sin secciones paralelas), un estudiante matriculado en el grado
     * pertenece a TODOS esos cursos automáticamente -- no hace falta
     * asignarle explícitamente el horario de cada uno. Solo cuando un
     * MISMO curso tiene varias secciones (ver el siguiente test) hace
     * falta la asignación explícita.
     */
    public function test_estudiantes_del_horario_incluye_al_matriculado_en_otro_curso_del_mismo_grado(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();

        $curso1 = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $curso2 = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $grado->id,
            'ciclo_id' => $ciclo->id,
        ]);

        $estudiantesDelOtroCurso = $this->service()->estudiantesDelHorario($curso2);

        $this->assertTrue($estudiantesDelOtroCurso->contains('id', $estudiante->id));
    }

    /**
     * Cuando el MISMO curso tiene dos secciones (dos Horario con igual
     * curso_id+grado_id+ciclo_id), ya no basta con estar matriculado en el
     * grado: cada estudiante debe aparecer solo en la sección que se le
     * asignó explícitamente, no en ambas.
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

    public function test_registrar_crea_un_registro_de_asistencia_por_estudiante(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $this->service()->registrar($horario, '2026-09-01', [
            $estudiante->id => EstadoAsistenciaEnum::PRESENTE->value,
        ]);

        $this->assertDatabaseHas('asistencias', [
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'fecha' => '2026-09-01',
            'estado' => 'presente',
        ]);
    }

    public function test_registrar_dos_veces_la_misma_fecha_actualiza_en_vez_de_duplicar(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $this->service()->registrar($horario, '2026-09-01', [
            $estudiante->id => EstadoAsistenciaEnum::PRESENTE->value,
        ]);
        $this->service()->registrar($horario, '2026-09-01', [
            $estudiante->id => EstadoAsistenciaEnum::TARDANZA->value,
        ]);

        $this->assertDatabaseCount('asistencias', 1);
        $this->assertDatabaseHas('asistencias', ['estado' => 'tardanza']);
    }

    public function test_registrar_guarda_la_observacion_y_el_documento_de_una_falta_justificada(): void
    {
        Storage::fake('public');

        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $this->service()->registrar(
            $horario,
            '2026-09-01',
            [$estudiante->id => EstadoAsistenciaEnum::JUSTIFICADO->value],
            [$estudiante->id => 'Cita médica'],
            [$estudiante->id => UploadedFile::fake()->create('constancia.pdf', 100, 'application/pdf')],
        );

        $this->assertDatabaseHas('asistencias', [
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'estado' => 'justificado',
            'observacion' => 'Cita médica',
        ]);

        $asistencia = Asistencia::query()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->assertNotNull($asistencia->getFirstMedia('justificante'));
    }

    public function test_registrar_sin_documento_no_borra_el_justificante_ya_subido(): void
    {
        Storage::fake('public');

        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();

        $service->registrar(
            $horario,
            '2026-09-01',
            [$estudiante->id => EstadoAsistenciaEnum::JUSTIFICADO->value],
            [$estudiante->id => 'Cita médica'],
            [$estudiante->id => UploadedFile::fake()->create('constancia.pdf', 100, 'application/pdf')],
        );

        $service->registrar(
            $horario,
            '2026-09-01',
            [$estudiante->id => EstadoAsistenciaEnum::JUSTIFICADO->value],
            [$estudiante->id => 'Cita médica reprogramada'],
        );

        $asistencia = Asistencia::query()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->assertSame('Cita médica reprogramada', $asistencia->observacion);
        $this->assertNotNull($asistencia->getFirstMedia('justificante'));
    }

    public function test_resumen_estudiante_calcula_el_porcentaje_de_asistencia(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();

        $service->registrar($horario, '2026-09-01', [$estudiante->id => EstadoAsistenciaEnum::PRESENTE->value]);
        $service->registrar($horario, '2026-09-02', [$estudiante->id => EstadoAsistenciaEnum::FALTA->value]);
        $service->registrar($horario, '2026-09-03', [$estudiante->id => EstadoAsistenciaEnum::TARDANZA->value]);
        $service->registrar($horario, '2026-09-04', [$estudiante->id => EstadoAsistenciaEnum::PRESENTE->value]);

        $resumen = $service->resumenEstudiante($estudiante, $horario);

        $this->assertSame(4, $resumen['total']);
        $this->assertSame(3, $resumen['asistio']);
        $this->assertSame(75.0, $resumen['porcentaje']);
    }

    public function test_fechas_registradas_devuelve_las_fechas_unicas_ordenadas_descendente(): void
    {
        $horario = Horario::factory()->create();
        $estudianteUno = Estudiante::factory()->create();
        $estudianteDos = Estudiante::factory()->create();
        $service = $this->service();

        $service->registrar($horario, '2026-09-01', [
            $estudianteUno->id => EstadoAsistenciaEnum::PRESENTE->value,
            $estudianteDos->id => EstadoAsistenciaEnum::PRESENTE->value,
        ]);
        $service->registrar($horario, '2026-09-03', [
            $estudianteUno->id => EstadoAsistenciaEnum::PRESENTE->value,
        ]);

        $fechas = $service->fechasRegistradas($horario);

        $this->assertSame(['2026-09-03', '2026-09-01'], $fechas->all());
    }

    public function test_fechas_de_clase_solo_devuelve_domingos_cuando_el_horario_es_solo_domingo(): void
    {
        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => now()->subMonths(2)->format('Y-m-d'),
            'fecha_fin' => now()->addMonths(2)->format('Y-m-d'),
        ]);
        $horario = $this->horarioConDias(['ciclo_id' => $ciclo->id], [DiaSemanaEnum::DOMINGO]);

        $fechas = $this->service()->fechasDeClase($horario, 4);

        $this->assertCount(4, $fechas);
        foreach ($fechas as $fecha) {
            $this->assertSame(0, Carbon::parse($fecha)->dayOfWeek);
        }
        $this->assertSame($fechas->all(), $fechas->sortDesc()->values()->all());
    }

    public function test_fechas_de_clase_respeta_los_dos_dias_cuando_el_horario_tiene_dos_dias(): void
    {
        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => now()->subMonths(2)->format('Y-m-d'),
            'fecha_fin' => now()->addMonths(2)->format('Y-m-d'),
        ]);
        $horario = $this->horarioConDias(['ciclo_id' => $ciclo->id], [DiaSemanaEnum::LUNES, DiaSemanaEnum::MIERCOLES]);

        $fechas = $this->service()->fechasDeClase($horario, 6);

        $this->assertCount(6, $fechas);
        foreach ($fechas as $fecha) {
            $this->assertContains(Carbon::parse($fecha)->dayOfWeek, [1, 3]);
        }
    }

    public function test_fechas_de_clase_no_se_pasa_del_fin_del_ciclo(): void
    {
        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => now()->subMonths(6)->format('Y-m-d'),
            'fecha_fin' => now()->subMonths(3)->format('Y-m-d'),
        ]);
        $horario = $this->horarioConDias(['ciclo_id' => $ciclo->id], [DiaSemanaEnum::DOMINGO]);

        $fechas = $this->service()->fechasDeClase($horario, 8);

        $this->assertTrue($fechas->isNotEmpty());
        foreach ($fechas as $fecha) {
            $this->assertTrue(Carbon::parse($fecha)->lte($ciclo->fecha_fin));
        }
    }

    /**
     * @return array{0: Horario, 1: Estudiante, 2: Carbon}
     */
    private function horarioMatriculadoUnDomingo(): array
    {
        $domingo = Carbon::now()->next(Carbon::SUNDAY);

        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => $domingo->copy()->subMonth()->format('Y-m-d'),
            'fecha_fin' => $domingo->copy()->addMonth()->format('Y-m-d'),
        ]);
        $horario = $this->horarioConDias(['ciclo_id' => $ciclo->id], [DiaSemanaEnum::DOMINGO]);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        return [$horario, $estudiante, $domingo];
    }

    public function test_horario_en_curso_del_estudiante_detecta_la_clase_dentro_de_su_horario(): void
    {
        [$horario, $estudiante, $domingo] = $this->horarioMatriculadoUnDomingo();

        $this->travelTo($domingo->copy()->setTime(18, 5));

        $encontrado = $this->service()->horarioEnCursoDelEstudiante($estudiante);

        $this->assertNotNull($encontrado);
        $this->assertSame($horario->id, $encontrado->id);
    }

    public function test_horario_en_curso_del_estudiante_devuelve_null_fuera_de_horario(): void
    {
        [, $estudiante, $domingo] = $this->horarioMatriculadoUnDomingo();

        $this->travelTo($domingo->copy()->setTime(10, 0));

        $this->assertNull($this->service()->horarioEnCursoDelEstudiante($estudiante));
    }

    public function test_autorregistrar_marca_presente_dentro_de_la_tolerancia(): void
    {
        [$horario, $estudiante, $domingo] = $this->horarioMatriculadoUnDomingo();

        $this->travelTo($domingo->copy()->setTime(18, 5));

        $asistencia = $this->service()->autorregistrar($horario, $estudiante);

        $this->assertSame(EstadoAsistenciaEnum::PRESENTE, $asistencia->estado);
    }

    public function test_autorregistrar_marca_tardanza_pasada_la_tolerancia(): void
    {
        [$horario, $estudiante, $domingo] = $this->horarioMatriculadoUnDomingo();

        $this->travelTo($domingo->copy()->setTime(18, 20));

        $asistencia = $this->service()->autorregistrar($horario, $estudiante);

        $this->assertSame(EstadoAsistenciaEnum::TARDANZA, $asistencia->estado);
    }

    public function test_autorregistrar_no_sobrescribe_un_registro_ya_hecho_por_el_docente(): void
    {
        [$horario, $estudiante, $domingo] = $this->horarioMatriculadoUnDomingo();

        Asistencia::factory()->create([
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'fecha' => $domingo->format('Y-m-d'),
            'estado' => EstadoAsistenciaEnum::FALTA,
        ]);

        $this->travelTo($domingo->copy()->setTime(18, 5));

        $asistencia = $this->service()->autorregistrar($horario, $estudiante);

        $this->assertSame(EstadoAsistenciaEnum::FALTA, $asistencia->estado);
        $this->assertDatabaseCount('asistencias', 1);
    }
}
