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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EvaluacionesFlujoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_el_docente_crea_una_evaluacion_y_registra_notas(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $this->actingAs($docente);

        $component = Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación mensual — julio')
            ->set('nuevaFecha', '2026-07-15')
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', ['nombre' => 'Evaluación mensual — julio']);

        $evaluacionId = $component->get('evaluacionId');

        $component
            ->set("notas.{$estudiante->id}", '17.5')
            ->set("observaciones.{$estudiante->id}", 'Buen desempeño')
            ->call('guardarNotas')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evaluacionId,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 17.5,
            'observaciones' => 'Buen desempeño',
        ]);
    }

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     */
    private function archivoExcel(array $encabezados, array $filas): UploadedFile
    {
        $hoja = new Spreadsheet;
        $hoja->getActiveSheet()->fromArray($encabezados, null, 'A1');
        $hoja->getActiveSheet()->fromArray($filas, null, 'A2');

        $ruta = tempnam(sys_get_temp_dir(), 'notas_test_').'.xlsx';
        (new Xlsx($hoja))->save($ruta);

        $archivo = UploadedFile::fake()->createWithContent('notas.xlsx', file_get_contents($ruta));
        unlink($ruta);

        return $archivo;
    }

    public function test_el_docente_importa_notas_desde_un_archivo(): void
    {
        Storage::fake('local');

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $estudiante = Estudiante::factory()->create(['dni' => '87654321']);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $this->actingAs($docente);

        $component = Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación por Google Forms')
            ->set('nuevaFecha', '2026-07-15')
            ->call('crear');

        $archivo = $this->archivoExcel(['dni', 'nota', 'observaciones'], [
            ['87654321', '18.5', 'Importado'],
        ]);

        $component
            ->set('archivoNotas', $archivo)
            ->call('importarNotas')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 18.5,
            'observaciones' => 'Importado',
        ]);
    }

    public function test_un_docente_dueno_de_otro_horario_no_puede_importar_notas(): void
    {
        Storage::fake('local');

        $docenteOwner = User::factory()->create();
        $docenteOwner->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docenteOwner->id]);

        $evaluacionService = $this->app->make(EvaluacionService::class);
        $evaluacionService->crear($horario, 'Evaluación', '2026-07-15');

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($otroDocente);

        $archivo = $this->archivoExcel(['dni', 'nota'], [['12345678', '15']]);

        rescue(fn () => Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('archivoNotas', $archivo)
            ->call('importarNotas'), report: false);

        $this->assertDatabaseCount('calificaciones', 0);
    }

    public function test_no_permite_registrar_una_nota_fuera_del_rango_0_20(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $this->actingAs($docente);

        $component = Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación')
            ->set('nuevaFecha', '2026-07-15')
            ->call('crear');

        $component
            ->set("notas.{$estudiante->id}", '25')
            ->call('guardarNotas')
            ->assertHasErrors(["notas.{$estudiante->id}"]);
    }

    public function test_el_docente_crea_una_evaluacion_con_enlace_externo(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación mensual')
            ->set('nuevaFecha', '2026-07-15')
            ->set('nuevoEnlace', 'https://forms.test/examen')
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', [
            'nombre' => 'Evaluación mensual',
            'enlace_externo' => 'https://forms.test/examen',
        ]);
    }

    public function test_no_permite_un_enlace_externo_invalido(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación mensual')
            ->set('nuevaFecha', '2026-07-15')
            ->set('nuevoEnlace', 'no-es-una-url')
            ->call('crear')
            ->assertHasErrors(['nuevoEnlace']);
    }

    public function test_el_docente_puede_editar_el_enlace_de_una_evaluacion_existente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente);

        $component = Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación mensual')
            ->set('nuevaFecha', '2026-07-15')
            ->call('crear');

        $evaluacionId = $component->get('evaluacionId');

        $component
            ->set('enlaceEditar', 'https://forms.test/actualizado')
            ->call('actualizarEnlace')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', [
            'id' => $evaluacionId,
            'enlace_externo' => 'https://forms.test/actualizado',
        ]);
    }

    public function test_un_estudiante_matriculado_no_ve_el_enlace_de_una_evaluacion_en_borrador(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $this->actingAs($docente);
        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación mensual')
            ->set('nuevaFecha', '2026-07-15')
            ->set('nuevoEnlace', 'https://forms.test/examen')
            ->call('crear');

        $this->actingAs($usuario);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->assertDontSee('Evaluaciones para rendir')
            ->assertDontSee('https://forms.test/examen');
    }

    public function test_un_estudiante_matriculado_ve_el_enlace_recien_cuando_se_publica(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación mensual', now()->format('Y-m-d'), 'https://forms.test/examen');
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($usuario);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->assertSee('Evaluaciones para rendir')
            ->assertSee('https://forms.test/examen');
    }

    public function test_el_docente_ve_las_evaluaciones_agrupadas_por_semana_en_orden_ascendente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $service = $this->app->make(EvaluacionService::class);
        $service->crear($horario, 'Evaluación semana 2', '2026-07-15', null, null, 2);
        $service->crear($horario, 'Evaluación sin semana', '2026-07-10');

        $this->actingAs($docente);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->assertSeeInOrder(['Bienvenida', 'Evaluación sin semana', 'Semana 2', 'Evaluación semana 2']);
    }

    public function test_un_estudiante_ve_las_evaluaciones_para_rendir_agrupadas_por_semana(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $service = $this->app->make(EvaluacionService::class);
        $evaluacion = $service->crear($horario, 'Evaluación semana 3', now()->format('Y-m-d'), 'https://forms.test/examen', null, 3);
        $service->publicar($evaluacion);

        $this->actingAs($usuario);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->assertSeeInOrder(['Evaluaciones para rendir', 'Semana 3', 'Evaluación semana 3']);
    }
}
