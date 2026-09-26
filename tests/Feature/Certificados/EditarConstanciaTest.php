<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EditarConstanciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): CertificadoService
    {
        return app(CertificadoService::class);
    }

    public function test_coordinador_puede_ver_el_detalle_y_editar_una_constancia(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create();
        $constancia = $this->service()->emitir(
            $estudiante, null, null, null, $coordinador, TipoDocumentoEnum::CONSTANCIA_MATRICULA,
        );

        $this->actingAs($coordinador);

        Volt::test('constancias.index')
            ->set('tab', 'historial')
            ->call('verDetalle', $constancia->id)
            ->assertSet('certificadoDetalleId', $constancia->id)
            ->assertSee($constancia->codigo_verificacion)
            ->call('iniciarEdicionCertificado', $constancia->id)
            ->set('editObservaciones', 'Corrección de constancia')
            ->call('guardarEdicionCertificado')
            ->assertHasNoErrors();

        $this->assertSame('Corrección de constancia', $constancia->fresh()->observaciones);
    }

    public function test_un_docente_no_puede_editar_una_constancia(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $docente->givePermissionTo('certificados.ver');
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $constancia = $this->service()->emitir(
            $estudiante, null, null, null, $emisor, TipoDocumentoEnum::CONSTANCIA_MATRICULA,
        );

        $this->actingAs($docente);

        rescue(fn () => Volt::test('constancias.index')
            ->set('tab', 'historial')
            ->call('iniciarEdicionCertificado', $constancia->id), report: false);

        $this->assertNull($constancia->fresh()->observaciones);
    }
}
