<?php

namespace Tests\Feature\Migraciones;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MigracionesPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cicloConPeriodoAbierto(): Ciclo
    {
        return Ciclo::factory()->activo()->create([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ]);
    }

    private function estudianteMatriculado(Ciclo $ciclo, Grado $grado, string $dni): Estudiante
    {
        $matriculas = $this->app->make(MatriculaService::class);

        $estudiante = $matriculas->registrarEstudiante(new RegistrarEstudianteData(
            nombres: 'Diego',
            apellidos: 'Torres Huamán',
            dni: new Dni($dni),
            email: uniqid('estudiante').'@example.com',
            fechaNacimiento: now()->subYears(25)->format('Y-m-d'),
            estadoCivil: null,
            direccion: null,
            celular: null,
            observaciones: null,
        ));

        $matriculas->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        return $estudiante;
    }

    public function test_rol_coordinador_puede_ver_migraciones(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($usuario)->get(route('migraciones.index'))->assertOk();
    }

    public function test_un_docente_no_puede_ver_migraciones(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($usuario)->get(route('migraciones.index'))->assertForbidden();
    }

    public function test_migrar_un_estudiante_individualmente_crea_la_nueva_matricula(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $gradoOrigen = Grado::factory()->create(['orden' => 1]);
        $gradoDestino = Grado::factory()->create(['orden' => 2]);
        $cicloOrigen = $this->cicloConPeriodoAbierto();
        $cicloDestino = $this->cicloConPeriodoAbierto();
        $estudiante = $this->estudianteMatriculado($cicloOrigen, $gradoOrigen, '55667711');

        $this->actingAs($usuario);

        Volt::test('migraciones.index')
            ->set('terminoBusqueda', 'Torres Huamán')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('cicloDestinoId', (string) $cicloDestino->id)
            ->set('gradoDestinoId', (string) $gradoDestino->id)
            ->call('migrarIndividual')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('matriculas', [
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $cicloDestino->id,
            'grado_id' => $gradoDestino->id,
        ]);
    }

    public function test_migrar_de_forma_masiva_reporta_el_resultado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $gradoOrigen = Grado::factory()->create(['orden' => 1]);
        $gradoDestino = Grado::factory()->create(['orden' => 2]);
        $cicloOrigen = $this->cicloConPeriodoAbierto();
        $cicloDestino = $this->cicloConPeriodoAbierto();
        $this->estudianteMatriculado($cicloOrigen, $gradoOrigen, '55667722');
        $this->estudianteMatriculado($cicloOrigen, $gradoOrigen, '55667733');

        $this->actingAs($usuario);

        Volt::test('migraciones.index')
            ->set('tab', 'masivo')
            ->set('cicloOrigenId', (string) $cicloOrigen->id)
            ->set('gradoOrigenId', (string) $gradoOrigen->id)
            ->set('masivoCicloDestinoId', (string) $cicloDestino->id)
            ->set('masivoGradoDestinoId', (string) $gradoDestino->id)
            ->call('migrarMasivo')
            ->assertHasNoErrors()
            ->assertSet('resultado.exitosos', 2);

        $this->assertDatabaseCount('matriculas', 4);
    }
}
