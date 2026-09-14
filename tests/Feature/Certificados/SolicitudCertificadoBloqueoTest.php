<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\PlanPago;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SolicitudCertificadoBloqueoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function estudiante(): array
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        return [$usuario, $estudiante];
    }

    public function test_un_estudiante_con_una_cuota_vencida_del_ciclo_actual_no_puede_solicitar_certificado(): void
    {
        [$usuario, $estudiante] = $this->estudiante();

        $cicloActivo = Ciclo::factory()->activo()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'ciclo_id' => $cicloActivo->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        $this->actingAs($usuario);

        Volt::test('certificados.mis-certificados')
            ->assertSee('No puedes solicitar documentos por ahora')
            ->set('motivo', 'Trámite laboral')
            ->set('metodoEntrega', 'fisica')
            ->call('solicitar')
            ->assertForbidden();

        $this->assertDatabaseCount('solicitudes_certificado', 0);
    }

    public function test_un_estudiante_con_pagos_al_dia_del_ciclo_actual_si_puede_solicitar_certificado(): void
    {
        [$usuario, $estudiante] = $this->estudiante();

        $cicloActivo = Ciclo::factory()->activo()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'ciclo_id' => $cicloActivo->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        $this->actingAs($usuario);

        Volt::test('certificados.mis-certificados')
            ->assertDontSee('No puedes solicitar documentos por ahora')
            ->set('motivo', 'Trámite laboral')
            ->set('metodoEntrega', 'fisica')
            ->call('solicitar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('solicitudes_certificado', 1);
    }

    public function test_una_cuota_vencida_de_un_ciclo_distinto_al_actual_no_bloquea_la_solicitud(): void
    {
        [$usuario, $estudiante] = $this->estudiante();

        Ciclo::factory()->activo()->create();
        $cicloNoActivo = Ciclo::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'ciclo_id' => $cicloNoActivo->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        $this->actingAs($usuario);

        Volt::test('certificados.mis-certificados')
            ->assertDontSee('No puedes solicitar documentos por ahora')
            ->set('motivo', 'Trámite laboral')
            ->set('metodoEntrega', 'fisica')
            ->call('solicitar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('solicitudes_certificado', 1);
    }
}
