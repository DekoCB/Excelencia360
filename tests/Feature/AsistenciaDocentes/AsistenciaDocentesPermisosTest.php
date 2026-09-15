<?php

namespace Tests\Feature\AsistenciaDocentes;

use App\Models\User;
use App\Modules\AsistenciaDocentes\Models\AsistenciaDocente;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AsistenciaDocentesPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function rolesConAcceso(): array
    {
        return [
            [RolEnum::DIRECCION->value],
            [RolEnum::COORDINADOR->value],
            [RolEnum::ADMINISTRATIVO->value],
            [RolEnum::DOCENTE->value],
        ];
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function rolesSinAcceso(): array
    {
        return [
            [RolEnum::TESORERIA->value],
            [RolEnum::ESTUDIANTE->value],
            [RolEnum::APODERADO->value],
        ];
    }

    /**
     * @dataProvider rolesConAcceso
     */
    public function test_roles_con_permiso_pueden_entrar(string $rol): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        $this->actingAs($usuario)
            ->get(route('asistencia-docentes.index'))
            ->assertOk();
    }

    /**
     * @dataProvider rolesSinAcceso
     */
    public function test_roles_sin_permiso_no_pueden_entrar(string $rol): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        $this->actingAs($usuario)
            ->get(route('asistencia-docentes.index'))
            ->assertForbidden();
    }

    public function test_coordinador_puede_registrar_asistencia_de_un_docente(): void
    {
        $docente = Docente::factory()->create();

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);

        Volt::test('asistencia-docentes.index')
            ->set('fecha', '2026-09-10')
            ->set("estados.{$docente->id}", 'tardanza')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asistencias_docentes', [
            'docente_id' => $docente->id,
            'fecha' => '2026-09-10',
            'estado' => 'tardanza',
            'registrado_por' => $coordinador->id,
        ]);
    }

    public function test_un_docente_no_puede_llamar_guardar_directamente(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::DOCENTE->value);
        Docente::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario);

        Volt::test('asistencia-docentes.index')
            ->set('fecha', '2026-09-10')
            ->call('guardar')
            ->assertForbidden();
    }

    public function test_un_docente_ve_su_propio_historial_pero_no_la_grilla_de_todos(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::DOCENTE->value);
        $docente = Docente::factory()->create(['user_id' => $usuario->id]);

        $otroDocenteUsuario = User::factory()->create(['name' => 'Otro Docente']);
        Docente::factory()->create(['user_id' => $otroDocenteUsuario->id]);

        AsistenciaDocente::factory()->for($docente, 'docente')->create(['fecha' => '2026-09-05']);

        $this->actingAs($usuario)
            ->get(route('asistencia-docentes.index'))
            ->assertOk()
            ->assertDontSee('Otro Docente');
    }

    public function test_el_enlace_asistencia_docente_aparece_en_el_menu_para_coordinador(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Asistencia docente');
    }
}
