<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Evaluaciones\Enums\TipoEvaluacionEnum;
use App\Modules\Evaluaciones\Enums\TipoPreguntaEnum;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Models\RespuestaEstudiante;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Evaluaciones\Services\PreguntaService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EvaluacionVirtualFlujoTest extends TestCase
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

    private function evaluacionVirtual(CursoVirtual $curso): Evaluacion
    {
        return $this->app->make(EvaluacionService::class)->crear($curso, 'Examen virtual', '2026-07-15', TipoEvaluacionEnum::VIRTUAL);
    }

    private function matricularEn(Horario $horario): Estudiante
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        return $estudiante;
    }

    public function test_el_docente_agrega_una_pregunta_de_opcion_unica(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->evaluacionVirtual($curso);

        $this->actingAs($docente);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set('preguntaEnunciado', '¿Capital del Perú?')
            ->set('preguntaTipo', 'opcion_unica')
            ->set('preguntaPuntaje', '10')
            ->set('alternativasTexto.0', 'Lima')
            ->set('alternativasCorrectas.0', true)
            ->set('alternativasTexto.1', 'Cusco')
            ->call('guardarPregunta')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('preguntas', [
            'evaluacion_id' => $evaluacion->id,
            'enunciado' => '¿Capital del Perú?',
        ]);
        $this->assertDatabaseHas('alternativas', ['texto' => 'Lima', 'es_correcta' => true]);
    }

    public function test_no_se_pueden_agregar_preguntas_a_una_evaluacion_ya_publicada(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->evaluacionVirtual($curso);
        $this->app->make(PreguntaService::class)->agregar($evaluacion, TipoPreguntaEnum::PREGUNTA_ABIERTA, 'Pregunta', 20.0);
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($docente);

        rescue(fn () => Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set('preguntaEnunciado', 'Otra')
            ->set('preguntaTipo', 'pregunta_abierta')
            ->set('preguntaPuntaje', '5')
            ->call('guardarPregunta'), report: false);

        $this->assertDatabaseCount('preguntas', 1);
    }

    public function test_no_se_puede_publicar_una_evaluacion_virtual_sin_preguntas(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->evaluacionVirtual($curso);

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);

        rescue(fn () => Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->call('publicar'), report: false);

        $this->assertDatabaseHas('evaluaciones', ['id' => $evaluacion->id, 'estado' => 'borrador']);
    }

    public function test_un_estudiante_rinde_una_evaluacion_totalmente_autocalificable_y_ve_su_nota_al_toque(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->evaluacionVirtual($curso);
        $estudiante = $this->matricularEn($curso->horario);

        $pregunta = $this->app->make(PreguntaService::class)->agregar($evaluacion, TipoPreguntaEnum::OPCION_UNICA, '¿2+2?', 20.0, [
            ['texto' => '4', 'es_correcta' => true],
            ['texto' => '5', 'es_correcta' => false],
        ]);
        $correcta = $pregunta->alternativas->firstWhere('es_correcta', true);
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($estudiante->user);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set("respuestasUnicas.{$pregunta->id}", (string) $correcta->id)
            ->call('enviarIntento')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 20.00,
        ]);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->assertSee('20.00');
    }

    public function test_un_estudiante_no_puede_rendir_dos_veces(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->evaluacionVirtual($curso);
        $estudiante = $this->matricularEn($curso->horario);

        $pregunta = $this->app->make(PreguntaService::class)->agregar($evaluacion, TipoPreguntaEnum::PREGUNTA_ABIERTA, 'Pregunta', 20.0);
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($estudiante->user);

        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set("respuestasTexto.{$pregunta->id}", 'Primera respuesta')
            ->call('enviarIntento');

        rescue(fn () => Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set("respuestasTexto.{$pregunta->id}", 'Segunda respuesta')
            ->call('enviarIntento'), report: false);

        $this->assertSame(1, RespuestaEstudiante::query()->where('pregunta_id', $pregunta->id)->count());
    }

    public function test_el_docente_califica_una_pregunta_abierta_pendiente_y_la_nota_queda_registrada(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);
        $evaluacion = $this->evaluacionVirtual($curso);
        $estudiante = $this->matricularEn($curso->horario);

        $pregunta = $this->app->make(PreguntaService::class)->agregar($evaluacion, TipoPreguntaEnum::PREGUNTA_ABIERTA, 'Explica algo.', 20.0);
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($estudiante->user);
        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set("respuestasTexto.{$pregunta->id}", 'Mi respuesta.')
            ->call('enviarIntento');

        $this->assertDatabaseCount('calificaciones', 0);

        $respuesta = $pregunta->respuestas()->where('estudiante_id', $estudiante->id)->firstOrFail();

        $this->actingAs($docente);
        Volt::test('aula-virtual.evaluacion', ['curso' => $curso, 'evaluacion' => $evaluacion])
            ->set("puntajesAbiertas.{$respuesta->id}", '15')
            ->call('calificarAbierta', $respuesta->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 15.00,
        ]);
    }
}
