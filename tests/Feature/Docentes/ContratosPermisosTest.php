<?php

namespace Tests\Feature\Docentes;

use App\Models\User;
use App\Modules\Docentes\Models\Contrato;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ContratosPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_rol_coordinador_puede_ver_el_listado_de_contratos(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)->get(route('contratos.index'))->assertOk();
    }

    public function test_un_docente_no_puede_ver_contratos(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)->get(route('contratos.index'))->assertForbidden();
    }

    public function test_el_buscador_de_contratos_muestra_sugerencias_y_filtra(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $docenteA = Docente::factory()->create();
        $docenteA->usuario->update(['name' => 'Karla Ponce']);
        Contrato::factory()->create(['docente_id' => $docenteA->id, 'tipo' => 'Contrato Tipo Uno Exclusivo']);

        $docenteB = Docente::factory()->create();
        $docenteB->usuario->update(['name' => 'Ivan Farfan']);
        Contrato::factory()->create(['docente_id' => $docenteB->id, 'tipo' => 'Contrato Tipo Dos Exclusivo']);

        $this->actingAs($coordinador);

        $html = Volt::test('contratos.index')->html();
        $this->assertStringContainsString('\u0022label\u0022:\u0022Karla Ponce\u0022', $html);
        $this->assertStringContainsString('\u0022label\u0022:\u0022Ivan Farfan\u0022', $html);

        // No se usa assertDontSee('Ivan Farfan'): su nombre tambien aparece
        // en el select de docentes del modal (sin filtrar por termino),
        // asi que se verifica sobre el "tipo" del contrato, que solo vive
        // en la fila de la tabla.
        Volt::test('contratos.index')
            ->set('termino', 'Karla')
            ->assertSee('Contrato Tipo Uno Exclusivo')
            ->assertDontSee('Contrato Tipo Dos Exclusivo');
    }

    public function test_coordinador_registra_un_contrato_con_documento(): void
    {
        Storage::fake('public');

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $docente = Docente::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('contratos.index')
            ->call('abrirModalCrear')
            ->set('docenteId', (string) $docente->id)
            ->set('tipo', 'Plazo fijo')
            ->set('fechaInicio', now()->format('Y-m-d'))
            ->set('monto', '1800')
            ->set('documento', UploadedFile::fake()->create('contrato.pdf', 200, 'application/pdf'))
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('mostrarModal', false);

        $contrato = Contrato::query()->where('docente_id', $docente->id)->first();

        $this->assertNotNull($contrato);
        $this->assertSame('Plazo fijo', $contrato->tipo);
        $this->assertNotNull($contrato->getFirstMedia('documento'));
    }

    public function test_no_permite_fecha_fin_anterior_a_la_fecha_de_inicio(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $docente = Docente::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('contratos.index')
            ->call('abrirModalCrear')
            ->set('docenteId', (string) $docente->id)
            ->set('tipo', 'Plazo fijo')
            ->set('fechaInicio', '2026-06-01')
            ->set('fechaFin', '2026-01-01')
            ->call('guardar')
            ->assertHasErrors('fechaFin');
    }

    public function test_coordinador_edita_un_contrato_existente(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $contrato = Contrato::factory()->create(['tipo' => 'Plazo fijo']);

        $this->actingAs($coordinador);

        Volt::test('contratos.index')
            ->call('abrirModalEditar', $contrato->id)
            ->set('tipo', 'Plazo indeterminado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Plazo indeterminado', $contrato->fresh()->tipo);
    }

    public function test_coordinador_elimina_un_contrato(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $contrato = Contrato::factory()->create();

        $this->actingAs($coordinador);

        Volt::test('contratos.index')->call('eliminar', $contrato->id);

        $this->assertDatabaseMissing('contratos', ['id' => $contrato->id]);
    }

    public function test_un_docente_no_puede_registrar_contratos(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente);

        rescue(fn () => Volt::test('contratos.index')
            ->call('abrirModalCrear'), report: false);

        $this->assertSame(0, Contrato::query()->count());
    }
}
