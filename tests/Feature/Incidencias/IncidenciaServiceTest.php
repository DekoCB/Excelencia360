<?php

namespace Tests\Feature\Incidencias;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\EntregaTarea;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Incidencias\Enums\TipoIncidenciaEnum;
use App\Modules\Incidencias\Services\IncidenciaService;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Jobs\EnviarMensajeWhatsappJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IncidenciaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): IncidenciaService
    {
        return $this->app->make(IncidenciaService::class);
    }

    public function test_crear_persiste_la_incidencia(): void
    {
        $estudiante = Estudiante::factory()->create();
        $reportadoPor = User::factory()->create();

        $incidencia = $this->service()->crear(
            $estudiante,
            $reportadoPor,
            TipoIncidenciaEnum::CONDUCTA,
            'Interrumpió la clase reiteradamente.',
            '2026-07-15',
        );

        $this->assertDatabaseHas('incidencias', [
            'id' => $incidencia->id,
            'estudiante_id' => $estudiante->id,
            'reportado_por' => $reportadoPor->id,
            'tipo' => TipoIncidenciaEnum::CONDUCTA->value,
        ]);
    }

    public function test_crear_para_un_estudiante_menor_de_edad_notifica_al_apoderado(): void
    {
        Queue::fake();

        $estudiante = Estudiante::factory()->menorDeEdad()->create();
        Apoderado::factory()->create(['estudiante_id' => $estudiante->id, 'celular' => '987654321']);

        $incidencia = $this->service()->crear(
            $estudiante,
            User::factory()->create(),
            TipoIncidenciaEnum::DISCIPLINA,
            'Falta de respeto a un compañero.',
            '2026-07-15',
        );

        $this->assertDatabaseHas('mensajes_whatsapp', [
            'estudiante_id' => $estudiante->id,
            'telefono' => '987654321',
            'tipo' => TipoMensajeWhatsappEnum::INCIDENCIA->value,
        ]);
        Queue::assertPushed(EnviarMensajeWhatsappJob::class, 1);
        $this->assertNotNull($incidencia->fresh()->notificado_apoderado_en);
    }

    public function test_crear_para_un_estudiante_mayor_de_edad_no_notifica(): void
    {
        Queue::fake();

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false]);

        $incidencia = $this->service()->crear(
            $estudiante,
            User::factory()->create(),
            TipoIncidenciaEnum::CONDUCTA,
            'Descripción de prueba.',
            '2026-07-15',
        );

        Queue::assertNotPushed(EnviarMensajeWhatsappJob::class);
        $this->assertNull($incidencia->fresh()->notificado_apoderado_en);
    }

    public function test_crear_para_un_menor_sin_telefono_de_contacto_no_notifica(): void
    {
        Queue::fake();

        $estudiante = Estudiante::factory()->menorDeEdad()->create(['celular' => null]);

        $this->service()->crear(
            $estudiante,
            User::factory()->create(),
            TipoIncidenciaEnum::CONDUCTA,
            'Descripción de prueba.',
            '2026-07-15',
        );

        Queue::assertNotPushed(EnviarMensajeWhatsappJob::class);
    }

    public function test_estudiantes_del_docente_solo_incluye_matriculados_en_sus_horarios(): void
    {
        $docente = User::factory()->create();
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $miAlumno = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $miAlumno->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        $otroAlumno = Estudiante::factory()->create();

        $estudiantes = $this->service()->estudiantesDelDocente($docente);

        $this->assertTrue($estudiantes->contains('id', $miAlumno->id));
        $this->assertFalse($estudiantes->contains('id', $otroAlumno->id));
    }

    public function test_tareas_no_realizadas_de_excluye_tareas_ya_entregadas(): void
    {
        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        $tareaVencidaSinEntregar = Tarea::factory()->create(['curso_virtual_id' => $curso->id, 'fecha_limite' => now()->subDay()]);
        $tareaVencidaEntregada = Tarea::factory()->create(['curso_virtual_id' => $curso->id, 'fecha_limite' => now()->subDay()]);
        EntregaTarea::factory()->create(['tarea_id' => $tareaVencidaEntregada->id, 'estudiante_id' => $estudiante->id]);
        Tarea::factory()->create(['curso_virtual_id' => $curso->id, 'fecha_limite' => now()->addDay()]);

        $pendientes = $this->service()->tareasNoRealizadasDe($estudiante);

        $this->assertTrue($pendientes->contains('id', $tareaVencidaSinEntregar->id));
        $this->assertFalse($pendientes->contains('id', $tareaVencidaEntregada->id));
        $this->assertCount(1, $pendientes);
    }

    public function test_evaluaciones_no_realizadas_de_excluye_evaluaciones_ya_calificadas(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        $sinCalificar = Evaluacion::factory()->create(['horario_id' => $horario->id, 'estado' => EstadoEvaluacionEnum::PUBLICADA]);
        $yaCalificada = Evaluacion::factory()->create(['horario_id' => $horario->id, 'estado' => EstadoEvaluacionEnum::PUBLICADA]);
        Calificacion::factory()->create(['evaluacion_id' => $yaCalificada->id, 'estudiante_id' => $estudiante->id]);
        Evaluacion::factory()->create(['horario_id' => $horario->id, 'estado' => EstadoEvaluacionEnum::BORRADOR]);

        $pendientes = $this->service()->evaluacionesNoRealizadasDe($estudiante);

        $this->assertTrue($pendientes->contains('id', $sinCalificar->id));
        $this->assertFalse($pendientes->contains('id', $yaCalificada->id));
        $this->assertCount(1, $pendientes);
    }
}
