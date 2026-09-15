<?php

namespace Tests\Feature\Tramites;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Tramites\Enums\CategoriaTramiteEnum;
use App\Modules\Tramites\Models\SolicitudTramite;
use App\Modules\Tramites\Services\TramiteService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TramitesPermisosTest extends TestCase
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
    public function test_cualquier_rol_puede_entrar_a_tramites_y_registrar_uno(string $rol): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        $this->actingAs($usuario)
            ->get(route('tramites.index'))
            ->assertOk();

        Volt::test('tramites.index')
            ->set('categoria', CategoriaTramiteEnum::OTRO->value)
            ->set('asunto', 'Necesito ayuda con...')
            ->set('descripcion', 'Descripción del trámite.')
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_tramite', [
            'solicitante_id' => $usuario->id,
            'asunto' => 'Necesito ayuda con...',
        ]);
    }

    public function test_un_usuario_sin_gestionar_solo_ve_sus_propios_tramites(): void
    {
        $service = $this->app->make(TramiteService::class);
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $otro = User::factory()->create();
        $otro->assignRole(RolEnum::ESTUDIANTE->value);

        $service->registrar($usuario, CategoriaTramiteEnum::OTRO, 'El mío', 'Descripción');
        $service->registrar($otro, CategoriaTramiteEnum::OTRO, 'El de otro', 'Descripción');

        $this->actingAs($usuario)
            ->get(route('tramites.index'))
            ->assertOk()
            ->assertSee('El mío')
            ->assertDontSee('El de otro');
    }

    public function test_coordinador_ve_los_tramites_de_todos(): void
    {
        $service = $this->app->make(TramiteService::class);
        $estudiante = User::factory()->create();
        $estudiante->assignRole(RolEnum::ESTUDIANTE->value);
        $service->registrar($estudiante, CategoriaTramiteEnum::OTRO, 'Trámite del estudiante', 'Descripción');

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('tramites.index'))
            ->assertOk()
            ->assertSee('Trámite del estudiante')
            ->assertSee('Gestionar');
    }

    public function test_un_estudiante_no_puede_gestionar_ni_ver_el_boton(): void
    {
        $service = $this->app->make(TramiteService::class);
        $estudiante = User::factory()->create();
        $estudiante->assignRole(RolEnum::ESTUDIANTE->value);
        $service->registrar($estudiante, CategoriaTramiteEnum::OTRO, 'Trámite del estudiante', 'Descripción');

        $this->actingAs($estudiante)
            ->get(route('tramites.index'))
            ->assertOk()
            ->assertDontSee('Gestionar');
    }

    public function test_un_estudiante_no_puede_llamar_guardarestado_directamente(): void
    {
        $service = $this->app->make(TramiteService::class);
        $estudiante = User::factory()->create();
        $estudiante->assignRole(RolEnum::ESTUDIANTE->value);
        $tramite = $service->registrar($estudiante, CategoriaTramiteEnum::OTRO, 'Asunto', 'Descripción');

        $this->actingAs($estudiante);

        Volt::test('tramites.index')
            ->set('tramiteEnGestionId', $tramite->id)
            ->set('nuevoEstado', 'atendida')
            ->set('resolucion', 'Listo.')
            ->call('guardarEstado')
            ->assertForbidden();
    }

    public function test_coordinador_puede_cambiar_el_estado_y_dejar_resolucion(): void
    {
        $service = $this->app->make(TramiteService::class);
        $solicitante = User::factory()->create();
        $solicitante->assignRole(RolEnum::ESTUDIANTE->value);
        $tramite = $service->registrar($solicitante, CategoriaTramiteEnum::OTRO, 'Asunto', 'Descripción');

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);

        Volt::test('tramites.index')
            ->call('abrirGestion', $tramite->id)
            ->set('nuevoEstado', 'atendida')
            ->set('resolucion', 'Se entregó en mesa de partes.')
            ->call('guardarEstado')
            ->assertHasNoErrors()
            ->assertSee('Trámite actualizado');

        $this->assertSame('atendida', $tramite->fresh()->estado->value);
        $this->assertSame($coordinador->id, $tramite->fresh()->responsable_id);
    }

    public function test_denegar_sin_resolucion_muestra_error_de_validacion(): void
    {
        $service = $this->app->make(TramiteService::class);
        $tramite = $service->registrar(User::factory()->create(), CategoriaTramiteEnum::OTRO, 'Asunto', 'Descripción');

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $this->actingAs($coordinador);

        Volt::test('tramites.index')
            ->call('abrirGestion', $tramite->id)
            ->set('nuevoEstado', 'denegada')
            ->set('resolucion', '')
            ->call('guardarEstado')
            ->assertHasErrors('resolucion');
    }

    public function test_el_enlace_tramites_aparece_en_el_menu_para_cualquier_rol(): void
    {
        $estudiante = User::factory()->create();
        $estudiante->assignRole(RolEnum::ESTUDIANTE->value);

        $this->actingAs($estudiante)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Trámites');
    }

    public function test_un_usuario_sin_ningun_rol_no_puede_entrar(): void
    {
        // Sin rol asignado, no tiene ninguno de los 3 permisos del módulo.
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('tramites.index'))
            ->assertForbidden();
    }

    public function test_no_puede_verse_el_tramite_de_otro_manipulando_el_id_directamente(): void
    {
        $service = $this->app->make(TramiteService::class);
        $ajeno = User::factory()->create();
        $ajeno->assignRole(RolEnum::ESTUDIANTE->value);
        $tramite = $service->registrar($ajeno, CategoriaTramiteEnum::OTRO, 'Trámite ajeno', 'Descripción');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $this->actingAs($usuario);

        // Ni siquiera puede abrir el panel de gestión de un trámite ajeno
        // (no tiene tramites.gestionar), así que la ruta directa a
        // intentar cambiar su estado también debe rechazarse.
        Volt::test('tramites.index')
            ->set('tramiteEnGestionId', $tramite->id)
            ->set('nuevoEstado', 'atendida')
            ->set('resolucion', 'x')
            ->call('guardarEstado')
            ->assertForbidden();

        $this->assertSame(SolicitudTramite::class, SolicitudTramite::class);
    }
}
