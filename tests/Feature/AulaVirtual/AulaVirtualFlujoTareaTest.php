<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Enums\EstadoEntregaEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\AulaVirtual\Services\TareaService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\PlanPago;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AulaVirtualFlujoTareaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cursoConEstudianteMatriculado(): array
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);

        $usuarioEstudiante = User::factory()->create();
        $usuarioEstudiante->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuarioEstudiante->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $horario->ciclo_id,
            'grado_id' => $horario->grado_id,
        ]);

        return [$docente, $curso, $usuarioEstudiante, $estudiante];
    }

    public function test_el_estudiante_entrega_la_tarea_y_el_docente_la_califica(): void
    {
        Storage::fake('public');

        [$docente, $curso, $usuarioEstudiante] = $this->cursoConEstudianteMatriculado();

        $tarea = Tarea::factory()->create([
            'curso_virtual_id' => $curso->id,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);

        $this->actingAs($usuarioEstudiante);

        Volt::test('aula-virtual.tarea', ['curso' => $curso, 'tarea' => $tarea])
            ->set('comentario', 'Adjunto mi ensayo')
            ->set('archivo', UploadedFile::fake()->create('ensayo.pdf', 200, 'application/pdf'))
            ->call('entregar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entregas_tarea', [
            'tarea_id' => $tarea->id,
            'comentario' => 'Adjunto mi ensayo',
        ]);

        $entrega = $tarea->entregas()->firstOrFail();

        $this->actingAs($docente);

        Volt::test('aula-virtual.tarea', ['curso' => $curso, 'tarea' => $tarea])
            ->set("notas.{$entrega->id}", '17.5')
            ->call('calificar', $entrega->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entregas_tarea', [
            'id' => $entrega->id,
            'nota' => 17.5,
            'estado' => 'calificado',
        ]);
    }

    public function test_no_permite_entregar_un_php_disfrazado_de_tarea(): void
    {
        Storage::fake('public');

        [, $curso, $usuarioEstudiante] = $this->cursoConEstudianteMatriculado();

        $tarea = Tarea::factory()->create([
            'curso_virtual_id' => $curso->id,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);

        $this->actingAs($usuarioEstudiante);

        Volt::test('aula-virtual.tarea', ['curso' => $curso, 'tarea' => $tarea])
            ->set('archivo', UploadedFile::fake()->create('malicioso.php', 10, 'application/x-php'))
            ->call('entregar')
            ->assertHasErrors('archivo');

        $this->assertDatabaseCount('entregas_tarea', 0);
    }

    public function test_un_estudiante_no_puede_calificar_su_propia_entrega(): void
    {
        [, $curso, $usuarioEstudiante, $estudiante] = $this->cursoConEstudianteMatriculado();

        $tarea = Tarea::factory()->create([
            'curso_virtual_id' => $curso->id,
            'fecha_limite' => now()->addDay(),
        ]);

        $this->actingAs($usuarioEstudiante);

        $entrega = app(TareaService::class)->entregar($tarea, $estudiante, null, null);

        rescue(fn () => Volt::test('aula-virtual.tarea', ['curso' => $curso, 'tarea' => $tarea])
            ->set("notas.{$entrega->id}", '20')
            ->call('calificar', $entrega->id), report: false);

        $this->assertNull($entrega->fresh()->nota);
        $this->assertSame(EstadoEntregaEnum::ENTREGADO, $entrega->fresh()->estado);
    }

    public function test_un_estudiante_con_una_cuota_vencida_del_ciclo_actual_no_puede_entregar_la_tarea(): void
    {
        Storage::fake('public');

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $cicloActivo = Ciclo::factory()->activo()->create();
        $horario = Horario::factory()->create(['docente_id' => $docente->id, 'ciclo_id' => $cicloActivo->id]);
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);

        $usuarioEstudiante = User::factory()->create();
        $usuarioEstudiante->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuarioEstudiante->id]);
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $horario->ciclo_id,
            'grado_id' => $horario->grado_id,
        ]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        $tarea = Tarea::factory()->create([
            'curso_virtual_id' => $curso->id,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);

        $this->actingAs($usuarioEstudiante);

        Volt::test('aula-virtual.tarea', ['curso' => $curso, 'tarea' => $tarea])
            ->assertSee('No puedes entregar esta tarea por ahora')
            ->set('comentario', 'Adjunto mi ensayo')
            ->call('entregar')
            ->assertForbidden();

        $this->assertDatabaseCount('entregas_tarea', 0);
    }
}
