<?php

namespace Tests\Feature\Notificaciones;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificacionesPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_coordinador_puede_ver_notificaciones(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('notificaciones.index'))
            ->assertOk();
    }

    public function test_coordinador_puede_gestionar_plantillas(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('notificaciones.plantillas'))
            ->assertOk();
    }

    public function test_administrativo_puede_ver_notificaciones(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get(route('notificaciones.index'))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_notificaciones(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('notificaciones.index'))
            ->assertForbidden();
    }

    public function test_un_estudiante_no_puede_ver_notificaciones(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);

        $this->actingAs($usuario)
            ->get(route('notificaciones.index'))
            ->assertForbidden();
    }

    public function test_un_estudiante_puede_ver_sus_propios_mensajes(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        MensajeWhatsapp::factory()->create(['estudiante_id' => $estudiante->id, 'contenido' => 'Recordatorio de pago']);

        $this->actingAs($usuario)
            ->get(route('notificaciones.mis-mensajes'))
            ->assertOk()
            ->assertSee('Recordatorio de pago');
    }

    public function test_un_estudiante_no_ve_mensajes_de_otro_estudiante(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);
        $otroEstudiante = Estudiante::factory()->create();
        MensajeWhatsapp::factory()->create(['estudiante_id' => $otroEstudiante->id, 'contenido' => 'Mensaje ajeno']);

        $this->actingAs($usuario)
            ->get(route('notificaciones.mis-mensajes'))
            ->assertOk()
            ->assertDontSee('Mensaje ajeno');
    }

    public function test_un_docente_no_puede_ver_mis_mensajes(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('notificaciones.mis-mensajes'))
            ->assertForbidden();
    }

    public function test_la_pestana_de_mensajes_lista_y_filtra_por_tipo(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        MensajeWhatsapp::factory()->create([
            'tipo' => TipoMensajeWhatsappEnum::RECORDATORIO,
            'contenido' => 'Recordatorio de cuota por vencer',
        ]);
        MensajeWhatsapp::factory()->create([
            'tipo' => TipoMensajeWhatsappEnum::ENTRANTE,
            'contenido' => 'Consulta de un apoderado',
        ]);

        $this->actingAs($coordinador);

        Volt::test('notificaciones.index')
            ->set('tab', 'mensajes')
            ->assertSee('Recordatorio de cuota por vencer')
            ->assertSee('Consulta de un apoderado')
            ->set('filtroTipo', TipoMensajeWhatsappEnum::RECORDATORIO->value)
            ->assertSee('Recordatorio de cuota por vencer')
            ->assertDontSee('Consulta de un apoderado');
    }
}
