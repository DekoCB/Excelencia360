<?php

namespace Tests\Feature\Asistencia;

use App\Models\User;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Enums\EstadoSolicitudJustificacionEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Asistencia\Services\JustificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JustificacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): JustificacionService
    {
        return $this->app->make(JustificacionService::class);
    }

    public function test_solicitar_crea_una_solicitud_pendiente_con_documento(): void
    {
        Storage::fake('public');

        $asistencia = Asistencia::factory()->create(['estado' => EstadoAsistenciaEnum::FALTA]);

        $solicitud = $this->service()->solicitar(
            $asistencia,
            'Cita médica',
            UploadedFile::fake()->create('constancia.pdf', 100, 'application/pdf'),
        );

        $this->assertSame(EstadoSolicitudJustificacionEnum::PENDIENTE, $solicitud->estado);
        $this->assertSame('Cita médica', $solicitud->motivo);
        $this->assertNotNull($solicitud->getFirstMedia('documento'));
    }

    public function test_solicitar_dos_veces_actualiza_la_misma_solicitud_en_vez_de_duplicar(): void
    {
        $asistencia = Asistencia::factory()->create(['estado' => EstadoAsistenciaEnum::FALTA]);
        $service = $this->service();

        $service->solicitar($asistencia, 'Primer motivo', null);
        $service->solicitar($asistencia, 'Motivo actualizado', null);

        $this->assertDatabaseCount('solicitudes_justificacion', 1);
        $this->assertDatabaseHas('solicitudes_justificacion', ['motivo' => 'Motivo actualizado']);
    }

    public function test_aprobar_marca_la_asistencia_como_justificada(): void
    {
        $asistencia = Asistencia::factory()->create(['estado' => EstadoAsistenciaEnum::FALTA]);
        $docente = User::factory()->create();
        $service = $this->service();

        $solicitud = $service->solicitar($asistencia, 'Cita médica', null);
        $service->aprobar($solicitud, $docente);

        $solicitud->refresh();
        $asistencia->refresh();

        $this->assertSame(EstadoSolicitudJustificacionEnum::APROBADA, $solicitud->estado);
        $this->assertSame($docente->id, $solicitud->revisado_por);
        $this->assertNotNull($solicitud->revisado_en);
        $this->assertSame(EstadoAsistenciaEnum::JUSTIFICADO, $asistencia->estado);
        $this->assertSame('Cita médica', $asistencia->observacion);
    }

    public function test_rechazar_no_cambia_el_estado_de_la_asistencia(): void
    {
        $asistencia = Asistencia::factory()->create(['estado' => EstadoAsistenciaEnum::FALTA]);
        $docente = User::factory()->create();
        $service = $this->service();

        $solicitud = $service->solicitar($asistencia, 'Cita médica', null);
        $service->rechazar($solicitud, $docente, 'No adjuntó comprobante válido');

        $solicitud->refresh();
        $asistencia->refresh();

        $this->assertSame(EstadoSolicitudJustificacionEnum::RECHAZADA, $solicitud->estado);
        $this->assertSame('No adjuntó comprobante válido', $solicitud->motivo_rechazo);
        $this->assertSame(EstadoAsistenciaEnum::FALTA, $asistencia->estado);
    }

    public function test_solicitud_pertenece_a_una_asistencia(): void
    {
        $asistencia = Asistencia::factory()->create(['estado' => EstadoAsistenciaEnum::FALTA]);
        $solicitud = $this->service()->solicitar($asistencia, 'Cita médica', null);

        $this->assertTrue($asistencia->fresh()->solicitudJustificacion->is($solicitud));
    }
}
