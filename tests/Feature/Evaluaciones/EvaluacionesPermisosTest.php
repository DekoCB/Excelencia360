<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Evaluaciones\Enums\TipoEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
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

    private function cursoDelDocente(User $docente): CursoVirtual
    {
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        return CursoVirtual::factory()->create(['horario_id' => $horario->id]);
    }

    private function crear(CursoVirtual $curso, string $nombre, string $fecha): Evaluacion
    {
        return $this->app->make(EvaluacionService::class)->crear($curso, $nombre, $fecha, TipoEvaluacionEnum::FISICO);
    }

    public function test_el_docente_dueno_del_curso_puede_ver_y_registrar_evaluaciones(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->crear($curso, 'Evaluación', '2026-07-15');

        $this->actingAs($docente)
            ->get(route('aula-virtual.evaluacion', [$curso, $evaluacion]))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_evaluaciones_de_otro_docente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($otroDocente);
        $evaluacion = $this->crear($curso, 'Evaluación', '2026-07-15');

        $this->actingAs($docente)
            ->get(route('aula-virtual.evaluacion', [$curso, $evaluacion]))
            ->assertForbidden();
    }

    public function test_un_estudiante_matriculado_puede_ver_una_evaluacion_publicada(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $evaluacion = $this->crear($curso, 'Evaluación', '2026-07-15');
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($usuario)
            ->get(route('aula-virtual.evaluacion', [$curso, $evaluacion]))
            ->assertOk();
    }

    public function test_un_estudiante_no_matriculado_no_puede_ver_la_evaluacion(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);
        $evaluacion = $this->crear($curso, 'Evaluación', '2026-07-15');

        $this->actingAs($usuario)
            ->get(route('aula-virtual.evaluacion', [$curso, $evaluacion]))
            ->assertForbidden();
    }

    public function test_coordinador_puede_supervisar_cualquier_curso(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->crear($curso, 'Evaluación', '2026-07-15');

        $this->actingAs($coordinador)
            ->get(route('aula-virtual.evaluacion', [$curso, $evaluacion]))
            ->assertOk();
    }

    public function test_coordinador_puede_publicar_una_evaluacion(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->crear($curso, 'Evaluación', '2026-07-15');

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->call('publicar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', ['id' => $evaluacion->id, 'estado' => 'publicada']);
    }

    public function test_un_docente_no_puede_publicar_su_propia_evaluacion(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->crear($curso, 'Evaluación', '2026-07-15');

        $this->actingAs($docente);

        rescue(fn () => Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->call('publicar'), report: false);

        $this->assertDatabaseHas('evaluaciones', ['id' => $evaluacion->id, 'estado' => 'borrador']);
    }
}
