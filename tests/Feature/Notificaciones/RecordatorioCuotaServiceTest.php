<?php

namespace Tests\Feature\Notificaciones;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Jobs\EnviarMensajeWhatsappJob;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use App\Modules\Notificaciones\Services\RecordatorioCuotaService;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\PlanPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecordatorioCuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cuotaPorVencerEn(int $dias): Cuota
    {
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);

        return Cuota::factory()->create([
            'plan_pago_id' => $plan->id,
            'fecha_vencimiento' => now()->addDays($dias)->format('Y-m-d'),
        ]);
    }

    public function test_envia_recordatorio_para_una_cuota_que_vence_dentro_del_rango(): void
    {
        Queue::fake();

        $cuota = $this->cuotaPorVencerEn(2);

        $enviados = app(RecordatorioCuotaService::class)->enviarRecordatorios(diasAntes: 3);

        $this->assertSame(1, $enviados);
        $this->assertDatabaseHas('mensajes_whatsapp', [
            'cuota_id' => $cuota->id,
            'tipo' => TipoMensajeWhatsappEnum::RECORDATORIO->value,
        ]);
        Queue::assertPushed(EnviarMensajeWhatsappJob::class, 1);
    }

    public function test_no_envia_recordatorio_para_una_cuota_fuera_del_rango(): void
    {
        Queue::fake();

        $this->cuotaPorVencerEn(10);

        $enviados = app(RecordatorioCuotaService::class)->enviarRecordatorios(diasAntes: 3);

        $this->assertSame(0, $enviados);
        Queue::assertNotPushed(EnviarMensajeWhatsappJob::class);
    }

    public function test_no_repite_el_recordatorio_el_mismo_dia(): void
    {
        Queue::fake();

        $cuota = $this->cuotaPorVencerEn(1);

        MensajeWhatsapp::factory()->create([
            'cuota_id' => $cuota->id,
            'tipo' => TipoMensajeWhatsappEnum::RECORDATORIO,
        ]);

        $enviados = app(RecordatorioCuotaService::class)->enviarRecordatorios(diasAntes: 3);

        $this->assertSame(0, $enviados);
    }

    public function test_repite_el_recordatorio_al_dia_siguiente_mientras_siga_pendiente(): void
    {
        Queue::fake();

        $cuota = $this->cuotaPorVencerEn(1);

        MensajeWhatsapp::factory()->create([
            'cuota_id' => $cuota->id,
            'tipo' => TipoMensajeWhatsappEnum::RECORDATORIO,
            'created_at' => now()->subDay(),
        ]);

        $enviados = app(RecordatorioCuotaService::class)->enviarRecordatorios(diasAntes: 3);

        $this->assertSame(1, $enviados);
    }

    public function test_sigue_recordando_una_cuota_ya_vencida_y_pendiente(): void
    {
        Queue::fake();

        $cuota = $this->cuotaPorVencerEn(-5);

        $enviados = app(RecordatorioCuotaService::class)->enviarRecordatorios(diasAntes: 3);

        $this->assertSame(1, $enviados);
        $mensaje = MensajeWhatsapp::where('cuota_id', $cuota->id)->first();
        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('venció el', $mensaje->contenido);
    }

    public function test_el_mensaje_de_una_cuota_por_vencer_no_dice_que_ya_vencio(): void
    {
        Queue::fake();

        $cuota = $this->cuotaPorVencerEn(2);

        app(RecordatorioCuotaService::class)->enviarRecordatorios(diasAntes: 3);

        $mensaje = MensajeWhatsapp::where('cuota_id', $cuota->id)->first();
        $this->assertNotNull($mensaje);
        $this->assertStringContainsString('vence el', $mensaje->contenido);
        $this->assertStringNotContainsString('venció el', $mensaje->contenido);
    }

    public function test_deja_de_recordar_una_cuota_ya_pagada(): void
    {
        Queue::fake();

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);

        Cuota::factory()->pagada()->create([
            'plan_pago_id' => $plan->id,
            'fecha_vencimiento' => now()->addDay()->format('Y-m-d'),
        ]);

        $enviados = app(RecordatorioCuotaService::class)->enviarRecordatorios(diasAntes: 3);

        $this->assertSame(0, $enviados);
        Queue::assertNotPushed(EnviarMensajeWhatsappJob::class);
    }

    public function test_usa_siete_dias_de_anticipacion_por_defecto(): void
    {
        Queue::fake();

        $cuotaDentroDelRango = $this->cuotaPorVencerEn(6);
        $this->cuotaPorVencerEn(10);

        $enviados = app(RecordatorioCuotaService::class)->enviarRecordatorios();

        $this->assertSame(1, $enviados);
        $this->assertDatabaseHas('mensajes_whatsapp', [
            'cuota_id' => $cuotaDentroDelRango->id,
            'tipo' => TipoMensajeWhatsappEnum::RECORDATORIO->value,
        ]);
    }
}
