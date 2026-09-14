<?php

namespace Tests\Feature\Asistencia;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Asistencia\Services\JustificacionService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AsistenciaPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_el_docente_dueno_del_horario_puede_ver_y_registrar_asistencia(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente)
            ->get(route('asistencia.show', $horario))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_la_asistencia_de_otro_docente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $otroDocente->id]);

        $this->actingAs($docente)
            ->get(route('asistencia.show', $horario))
            ->assertForbidden();
    }

    public function test_un_estudiante_matriculado_en_el_grado_y_ciclo_del_horario_puede_ver_su_resumen(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $this->actingAs($usuario)
            ->get(route('asistencia.show', $horario))
            ->assertOk();
    }

    public function test_un_estudiante_no_matriculado_en_ese_grado_y_ciclo_no_puede_ver_la_asistencia(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();

        $this->actingAs($usuario)
            ->get(route('asistencia.show', $horario))
            ->assertForbidden();
    }

    public function test_coordinador_puede_supervisar_cualquier_horario(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($coordinador)
            ->get(route('asistencia.show', $horario))
            ->assertOk();
    }

    /**
     * Regresión: el listado de estados solo se precargaba para quien podía
     * registrar asistencia (el docente dueño del horario), así que un
     * supervisor como Coordinador siempre veía todos los botones sin marcar,
     * incluso si ya había asistencia guardada para esa fecha.
     */
    public function test_coordinador_ve_los_estados_ya_registrados_al_supervisar(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $usuarioEstudiante = User::factory()->create();
        $estudiante = Estudiante::factory()->create(['user_id' => $usuarioEstudiante->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        $fecha = now()->format('Y-m-d');
        Asistencia::factory()->create([
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'fecha' => $fecha,
            'estado' => EstadoAsistenciaEnum::FALTA->value,
        ]);

        $this->actingAs($coordinador);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->assertSet("estados.{$estudiante->id}", EstadoAsistenciaEnum::FALTA->value);
    }

    public function test_el_docente_registra_una_falta_justificada_con_documento(): void
    {
        Storage::fake('public');

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $usuarioEstudiante = User::factory()->create();
        $estudiante = Estudiante::factory()->create(['user_id' => $usuarioEstudiante->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->set("estados.{$estudiante->id}", EstadoAsistenciaEnum::JUSTIFICADO->value)
            ->set("observaciones.{$estudiante->id}", 'Cita médica')
            ->set("justificantes.{$estudiante->id}", UploadedFile::fake()->create('constancia.pdf', 100, 'application/pdf'))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asistencias', [
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'estado' => 'justificado',
            'observacion' => 'Cita médica',
        ]);

        $asistencia = Asistencia::query()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->assertNotNull($asistencia->getFirstMedia('justificante'));
    }

    public function test_coordinador_puede_ver_el_listado(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('asistencia.index'))
            ->assertOk();
    }

    public function test_un_usuario_sin_permisos_de_asistencia_no_puede_ver_el_listado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($usuario)
            ->get(route('asistencia.index'))
            ->assertForbidden();
    }

    /**
     * Dirección tiene asistencia.registrar vía '*' sin ser docente: debía
     * mostrar la vista de supervisión, no la de "tus horarios" (vacía).
     */
    public function test_direccion_ve_la_supervision_general_no_la_vista_de_docente(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);
        Horario::factory()->create();

        $this->actingAs($direccion)
            ->get(route('asistencia.index'))
            ->assertOk()
            ->assertSee('Supervisión de asistencia')
            ->assertDontSee('Todavía no tienes horarios asignados este ciclo.');
    }

    public function test_un_estudiante_solicita_justificar_su_falta(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $usuarioEstudiante = User::factory()->create();
        $usuarioEstudiante->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuarioEstudiante->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        $asistencia = Asistencia::factory()->create([
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'estado' => EstadoAsistenciaEnum::FALTA->value,
        ]);

        $this->actingAs($usuarioEstudiante);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->call('abrirSolicitud', $asistencia->id)
            ->set('solicitudMotivo', 'Cita médica')
            ->call('enviarSolicitud')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_justificacion', [
            'asistencia_id' => $asistencia->id,
            'motivo' => 'Cita médica',
            'estado' => 'pendiente',
        ]);
    }

    public function test_el_docente_ve_y_aprueba_una_solicitud_pendiente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $estudiante = Estudiante::factory()->create();
        $asistencia = Asistencia::factory()->create([
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'estado' => EstadoAsistenciaEnum::FALTA->value,
        ]);

        $solicitud = $this->app->make(JustificacionService::class)
            ->solicitar($asistencia, 'Cita médica', null);

        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->assertSee('Cita médica')
            ->call('aprobarSolicitud', $solicitud->id);

        $this->assertDatabaseHas('asistencias', [
            'id' => $asistencia->id,
            'estado' => 'justificado',
        ]);
        $this->assertDatabaseHas('solicitudes_justificacion', [
            'id' => $solicitud->id,
            'estado' => 'aprobada',
            'revisado_por' => $docente->id,
        ]);
    }

    public function test_un_estudiante_no_puede_solicitar_justificacion_de_una_asistencia_que_no_es_falta(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $usuarioEstudiante = User::factory()->create();
        $usuarioEstudiante->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuarioEstudiante->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
            'estado' => 'aprobada',
        ]);

        $asistencia = Asistencia::factory()->create([
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'estado' => EstadoAsistenciaEnum::PRESENTE->value,
        ]);

        $this->actingAs($usuarioEstudiante);

        rescue(fn () => Volt::test('asistencia.show', ['horario' => $horario])
            ->call('abrirSolicitud', $asistencia->id)
            ->set('solicitudMotivo', 'Cita médica')
            ->call('enviarSolicitud'), report: false);

        $this->assertDatabaseCount('solicitudes_justificacion', 0);
    }

    public function test_el_docente_rechaza_una_solicitud_y_la_falta_no_cambia(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $estudiante = Estudiante::factory()->create();
        $asistencia = Asistencia::factory()->create([
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'estado' => EstadoAsistenciaEnum::FALTA->value,
        ]);

        $this->app->make(JustificacionService::class)
            ->solicitar($asistencia, 'Cita médica', null);

        $solicitud = $asistencia->fresh()->solicitudJustificacion;

        $this->actingAs($docente);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->call('rechazarSolicitud', $solicitud->id);

        $this->assertDatabaseHas('solicitudes_justificacion', ['id' => $solicitud->id, 'estado' => 'rechazada']);
        $this->assertDatabaseHas('asistencias', ['id' => $asistencia->id, 'estado' => 'falta']);
    }

    public function test_un_coordinador_no_puede_aprobar_solicitudes(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $estudiante = Estudiante::factory()->create();
        $asistencia = Asistencia::factory()->create([
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'estado' => EstadoAsistenciaEnum::FALTA->value,
        ]);

        $this->app->make(JustificacionService::class)
            ->solicitar($asistencia, 'Cita médica', null);

        $solicitud = $asistencia->fresh()->solicitudJustificacion;

        $this->actingAs($coordinador);

        Volt::test('asistencia.show', ['horario' => $horario])
            ->call('aprobarSolicitud', $solicitud->id)
            ->assertForbidden();
    }
}
