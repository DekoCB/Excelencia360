<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Pagos\Enums\EstadoSolicitudCambioMontoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Services\SolicitudCambioMontoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SolicitudCambioMontoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SolicitudCambioMontoService
    {
        return $this->app->make(SolicitudCambioMontoService::class);
    }

    private function aprobador(): int
    {
        return User::factory()->create()->id;
    }

    public function test_solicitar_no_cambia_el_monto_del_concepto(): void
    {
        $concepto = ConceptoPago::factory()->create(['monto_base' => 100]);

        $solicitud = $this->service()->solicitar($concepto, 150.0, $this->aprobador());

        $this->assertSame(EstadoSolicitudCambioMontoEnum::PENDIENTE, $solicitud->estado);
        $this->assertSame('100.00', $concepto->fresh()->monto_base);
        $this->assertSame('100.00', (string) $solicitud->monto_actual);
        $this->assertSame('150.00', (string) $solicitud->monto_propuesto);
    }

    public function test_no_permite_dos_solicitudes_pendientes_para_el_mismo_concepto(): void
    {
        $concepto = ConceptoPago::factory()->create(['monto_base' => 100]);
        $this->service()->solicitar($concepto, 150.0, $this->aprobador());

        $this->expectException(ValidationException::class);

        $this->service()->solicitar($concepto, 200.0, $this->aprobador());
    }

    public function test_aprobar_aplica_el_monto_propuesto_al_concepto(): void
    {
        $concepto = ConceptoPago::factory()->create(['monto_base' => 100]);
        $solicitud = $this->service()->solicitar($concepto, 150.0, $this->aprobador());

        $aprobada = $this->service()->aprobar($solicitud, $this->aprobador());

        $this->assertSame(EstadoSolicitudCambioMontoEnum::APROBADA, $aprobada->estado);
        $this->assertSame('150.00', $concepto->fresh()->monto_base);
    }

    public function test_rechazar_deja_el_monto_del_concepto_sin_cambios(): void
    {
        $concepto = ConceptoPago::factory()->create(['monto_base' => 100]);
        $solicitud = $this->service()->solicitar($concepto, 150.0, $this->aprobador());

        $rechazada = $this->service()->rechazar($solicitud, $this->aprobador(), 'No corresponde este ciclo');

        $this->assertSame(EstadoSolicitudCambioMontoEnum::RECHAZADA, $rechazada->estado);
        $this->assertSame('No corresponde este ciclo', $rechazada->motivo_rechazo);
        $this->assertSame('100.00', $concepto->fresh()->monto_base);
    }

    public function test_no_permite_aprobar_una_solicitud_ya_procesada(): void
    {
        $concepto = ConceptoPago::factory()->create(['monto_base' => 100]);
        $solicitud = $this->service()->solicitar($concepto, 150.0, $this->aprobador());
        $this->service()->aprobar($solicitud, $this->aprobador());

        $this->expectException(ValidationException::class);

        $this->service()->aprobar($solicitud, $this->aprobador());
    }

    public function test_pendientes_solo_incluye_solicitudes_sin_resolver(): void
    {
        $conceptoA = ConceptoPago::factory()->create(['monto_base' => 100]);
        $conceptoB = ConceptoPago::factory()->create(['monto_base' => 200]);

        $pendiente = $this->service()->solicitar($conceptoA, 150.0, $this->aprobador());
        $resuelta = $this->service()->solicitar($conceptoB, 250.0, $this->aprobador());
        $this->service()->aprobar($resuelta, $this->aprobador());

        $cola = $this->service()->pendientes();

        $this->assertTrue($cola->contains('id', $pendiente->id));
        $this->assertFalse($cola->contains('id', $resuelta->id));
    }
}
