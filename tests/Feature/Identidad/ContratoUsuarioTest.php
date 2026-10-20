<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Docentes\Models\Contrato;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Punto 10 del backlog: Contrato deja de ser exclusivo de Docente y se
 * gestiona desde la ficha de cualquier usuario de personal institucional
 * (ver User::esPersonalInstitucional()), no desde una pantalla propia.
 */
class ContratoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_la_ficha_de_un_docente_muestra_la_seccion_de_contratos(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($coordinador);

        Volt::test('usuarios.show', ['usuario' => $docente])
            ->assertSee('Contratos');
    }

    public function test_la_ficha_de_un_administrativo_tambien_muestra_contratos(): void
    {
        // Confirma que ya no es exclusivo de Docente.
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($coordinador);

        Volt::test('usuarios.show', ['usuario' => $administrativo])
            ->assertSee('Contratos');
    }

    public function test_la_ficha_de_un_estudiante_no_muestra_contratos(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = User::factory()->create();
        $estudiante->assignRole(RolEnum::ESTUDIANTE->value);

        $this->actingAs($coordinador);

        Volt::test('usuarios.show', ['usuario' => $estudiante])
            ->assertDontSee('Contratos');
    }

    public function test_coordinador_registra_un_contrato_desde_la_ficha_con_documento(): void
    {
        Storage::fake('public');

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($coordinador);

        Volt::test('usuarios.show', ['usuario' => $docente])
            ->call('abrirFormContratoCrear')
            ->set('tipo', 'Plazo fijo')
            ->set('fechaInicio', now()->format('Y-m-d'))
            ->set('monto', '1800')
            ->set('documento', UploadedFile::fake()->create('contrato.pdf', 200, 'application/pdf'))
            ->call('guardarContrato')
            ->assertHasNoErrors()
            ->assertSet('mostrarFormContrato', false);

        $contrato = Contrato::query()->where('user_id', $docente->id)->first();

        $this->assertNotNull($contrato);
        $this->assertSame('Plazo fijo', $contrato->tipo);
        $this->assertNotNull($contrato->getFirstMedia('documento'));
    }

    public function test_no_permite_fecha_fin_anterior_a_la_fecha_de_inicio(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($coordinador);

        Volt::test('usuarios.show', ['usuario' => $docente])
            ->call('abrirFormContratoCrear')
            ->set('tipo', 'Plazo fijo')
            ->set('fechaInicio', '2026-06-01')
            ->set('fechaFin', '2026-01-01')
            ->call('guardarContrato')
            ->assertHasErrors('fechaFin');
    }

    public function test_coordinador_edita_un_contrato_existente_desde_la_ficha(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $contrato = Contrato::factory()->create(['user_id' => $docente->id, 'tipo' => 'Plazo fijo']);

        $this->actingAs($coordinador);

        Volt::test('usuarios.show', ['usuario' => $docente])
            ->call('abrirFormContratoEditar', $contrato->id)
            ->set('tipo', 'Plazo indeterminado')
            ->call('guardarContrato')
            ->assertHasNoErrors();

        $this->assertSame('Plazo indeterminado', $contrato->fresh()->tipo);
    }

    public function test_coordinador_elimina_un_contrato_desde_la_ficha(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $contrato = Contrato::factory()->create(['user_id' => $docente->id]);

        $this->actingAs($coordinador);

        Volt::test('usuarios.show', ['usuario' => $docente])->call('eliminarContrato', $contrato->id);

        $this->assertDatabaseMissing('contratos', ['id' => $contrato->id]);
    }

    public function test_un_administrativo_sin_permiso_de_gestionar_contratos_no_puede_registrar(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($administrativo);

        rescue(fn () => Volt::test('usuarios.show', ['usuario' => $docente])
            ->call('abrirFormContratoCrear'), report: false);

        $this->assertSame(0, Contrato::query()->count());
    }

    public function test_el_modal_ver_tambien_permite_registrar_un_contrato(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($coordinador);

        Volt::test('usuarios.ficha-modal')
            ->call('abrir', $docente->id)
            ->assertSee('Contratos')
            ->call('abrirFormContratoCrear')
            ->set('tipo', 'Locación de servicios')
            ->set('fechaInicio', now()->format('Y-m-d'))
            ->call('guardarContrato')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contratos', ['user_id' => $docente->id, 'tipo' => 'Locación de servicios']);
    }
}
