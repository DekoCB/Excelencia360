<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EvaluacionesPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cursoDelDocente(User $docente): Horario
    {
        return Horario::factory()->create(['docente_id' => $docente->id]);
    }

    public function test_el_docente_dueno_del_horario_puede_ver_y_registrar_evaluaciones(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = $this->cursoDelDocente($docente);

        $this->actingAs($docente)
            ->get(route('evaluaciones.show', $horario))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_evaluaciones_de_otro_docente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $horario = $this->cursoDelDocente($otroDocente);

        $this->actingAs($docente)
            ->get(route('evaluaciones.show', $horario))
            ->assertForbidden();
    }

    public function test_un_estudiante_matriculado_puede_ver_el_horario(): void
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
            ->get(route('evaluaciones.show', $horario))
            ->assertOk();
    }

    public function test_un_estudiante_no_matriculado_no_puede_ver_el_horario(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();

        $this->actingAs($usuario)
            ->get(route('evaluaciones.show', $horario))
            ->assertForbidden();
    }

    public function test_coordinador_puede_supervisar_cualquier_horario(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = $this->cursoDelDocente($docente);

        $this->actingAs($coordinador)
            ->get(route('evaluaciones.show', $horario))
            ->assertOk();
    }

    public function test_un_usuario_sin_permisos_de_evaluaciones_no_puede_ver_el_listado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($usuario)
            ->get(route('evaluaciones.index'))
            ->assertForbidden();
    }

    /**
     * Dirección tiene evaluaciones.registrar vía '*' sin ser docente: debía
     * mostrar la vista de supervisión, no la de "tus horarios" (vacía).
     */
    public function test_direccion_ve_la_supervision_general_no_la_vista_de_docente(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);
        Horario::factory()->create();

        $this->actingAs($direccion)
            ->get(route('evaluaciones.index'))
            ->assertOk()
            ->assertSee('Supervisión de evaluaciones')
            ->assertDontSee('Todavía no tienes horarios asignados este ciclo.');
    }

    public function test_coordinador_puede_publicar_una_evaluacion(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = $this->cursoDelDocente($docente);
        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación', '2026-07-15');

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->call('seleccionar', $evaluacion->id)
            ->call('publicar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', ['id' => $evaluacion->id, 'estado' => 'publicada']);
    }

    public function test_un_docente_no_puede_publicar_su_propia_evaluacion(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = $this->cursoDelDocente($docente);
        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación', '2026-07-15');

        $this->actingAs($docente);

        rescue(fn () => Volt::test('evaluaciones.show', ['horario' => $horario])
            ->call('seleccionar', $evaluacion->id)
            ->call('publicar'), report: false);

        $this->assertDatabaseHas('evaluaciones', ['id' => $evaluacion->id, 'estado' => 'borrador']);
    }
}
