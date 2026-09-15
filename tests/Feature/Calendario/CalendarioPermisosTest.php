<?php

namespace Tests\Feature\Calendario;

use App\Models\User;
use App\Modules\Calendario\Models\EventoCalendario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CalendarioPermisosTest extends TestCase
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
    public static function todosLosRoles(): array
    {
        return [
            [RolEnum::DIRECCION->value],
            [RolEnum::COORDINADOR->value],
            [RolEnum::ADMINISTRATIVO->value],
            [RolEnum::TESORERIA->value],
            [RolEnum::DOCENTE->value],
            [RolEnum::ESTUDIANTE->value],
            [RolEnum::APODERADO->value],
        ];
    }

    /**
     * @dataProvider todosLosRoles
     */
    public function test_cualquier_rol_puede_ver_el_calendario(string $rol): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        $this->actingAs($usuario)
            ->get(route('calendario.index'))
            ->assertOk();
    }

    public function test_un_usuario_sin_ningun_rol_no_puede_entrar(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('calendario.index'))
            ->assertForbidden();
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function rolesSinGestionar(): array
    {
        return [
            [RolEnum::TESORERIA->value],
            [RolEnum::DOCENTE->value],
            [RolEnum::ESTUDIANTE->value],
            [RolEnum::APODERADO->value],
        ];
    }

    /**
     * @dataProvider rolesSinGestionar
     */
    public function test_roles_sin_gestionar_no_pueden_crear_eventos(string $rol): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);
        $this->actingAs($usuario);

        Volt::test('calendario.index')
            ->set('tipo', 'reunion')
            ->set('titulo', 'Intento no autorizado')
            ->set('fechaInicio', '2026-09-10')
            ->call('guardar')
            ->assertForbidden();
    }

    public function test_coordinador_puede_crear_un_evento(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);

        Volt::test('calendario.index')
            ->call('abrirFormNuevo')
            ->set('tipo', 'reunion')
            ->set('titulo', 'Reunión de coordinación')
            ->set('fechaInicio', '2026-09-10')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('eventos_calendario', [
            'titulo' => 'Reunión de coordinación',
            'creado_por' => $coordinador->id,
        ]);
    }

    public function test_fecha_fin_anterior_a_fecha_inicio_falla_la_validacion(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);

        Volt::test('calendario.index')
            ->call('abrirFormNuevo')
            ->set('tipo', 'reunion')
            ->set('titulo', 'Evento con rango inválido')
            ->set('fechaInicio', '2026-09-10')
            ->set('fechaFin', '2026-09-05')
            ->call('guardar')
            ->assertHasErrors('fechaFin');
    }

    public function test_un_docente_no_puede_editar_un_evento_existente(): void
    {
        $evento = EventoCalendario::factory()->create();

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $this->actingAs($docente);

        Volt::test('calendario.index')
            ->call('editarEvento', $evento->id)
            ->assertForbidden();
    }

    public function test_administrativo_puede_eliminar_un_evento(): void
    {
        $evento = EventoCalendario::factory()->create();

        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);
        $this->actingAs($administrativo);

        Volt::test('calendario.index')
            ->call('eliminar', $evento->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('eventos_calendario', ['id' => $evento->id]);
    }

    public function test_el_enlace_calendario_aparece_en_el_menu_para_cualquier_rol(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Calendario');
    }
}
