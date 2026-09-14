<?php

namespace Tests\Feature\Pagos;

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\TipoConceptoEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\CobranzaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CobranzaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_deuda_de_estudiante_incluye_cuotas_pendientes_y_pagos_pendientes_o_rechazados(): void
    {
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);

        Cuota::factory()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);
        // No debería contar: ya está pagada.
        Cuota::factory()->pagada()->create(['plan_pago_id' => $plan->id, 'numero' => 2]);

        Pago::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => EstadoPagoEnum::PENDIENTE]);
        Pago::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => EstadoPagoEnum::RECHAZADO]);
        // Ya fue aprobado: no cuenta como deuda, pero sí debe aparecer en pagosAprobados.
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id]);

        $deuda = app(CobranzaService::class)->deudaDeEstudiante($estudiante);

        $this->assertCount(1, $deuda['cuotasPendientes']);
        $this->assertCount(2, $deuda['pagosPendientes']);
        $this->assertCount(1, $deuda['pagosAprobados']);
    }

    public function test_deuda_de_estudiante_incluye_el_cargo_adicional_pendiente_con_su_saldo_real(): void
    {
        $estudiante = Estudiante::factory()->create();
        $cargo = CargoAdicional::factory()->create(['estudiante_id' => $estudiante->id, 'monto' => 80]);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'cargo_adicional_id' => $cargo->id, 'monto' => 30]);

        // No debería contar: ya está pagado del todo.
        CargoAdicional::factory()->pagado()->create(['estudiante_id' => $estudiante->id]);

        $deuda = app(CobranzaService::class)->deudaDeEstudiante($estudiante);

        $this->assertCount(1, $deuda['cargosAdicionalesPendientes']);
        $this->assertSame(50.0, $deuda['cargosAdicionalesPendientes']->first()->saldoPendiente());
    }

    public function test_deuda_de_estudiante_no_incluye_cuotas_ni_pagos_de_otro_estudiante(): void
    {
        $estudiante = Estudiante::factory()->create();
        $otro = Estudiante::factory()->create();

        $planOtro = PlanPago::factory()->create(['matricula_id' => Matricula::factory()->create(['estudiante_id' => $otro->id])->id]);
        Cuota::factory()->create(['plan_pago_id' => $planOtro->id]);
        Pago::factory()->create(['estudiante_id' => $otro->id, 'estado' => EstadoPagoEnum::PENDIENTE]);
        Pago::factory()->aprobado()->create(['estudiante_id' => $otro->id]);
        CargoAdicional::factory()->create(['estudiante_id' => $otro->id]);

        $deuda = app(CobranzaService::class)->deudaDeEstudiante($estudiante);

        $this->assertCount(0, $deuda['cuotasPendientes']);
        $this->assertCount(0, $deuda['pagosPendientes']);
        $this->assertCount(0, $deuda['pagosAprobados']);
        $this->assertCount(0, $deuda['cargosAdicionalesPendientes']);
    }

    public function test_deudores_por_concepto_mensualidad_filtra_por_grupo_y_grado(): void
    {
        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create();

        $matriculaA = Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);
        Cuota::factory()->create(['plan_pago_id' => PlanPago::factory()->create(['matricula_id' => $matriculaA->id])->id]);

        $matriculaB = Matricula::factory()->create(['grado_id' => $horarioB->grado_id, 'ciclo_id' => $horarioB->ciclo_id]);
        Cuota::factory()->create(['plan_pago_id' => PlanPago::factory()->create(['matricula_id' => $matriculaB->id])->id]);

        $concepto = ConceptoPago::factory()->create(['tipo' => TipoConceptoEnum::MENSUALIDAD]);

        $reporte = app(CobranzaService::class)->deudoresPorConceptos([$concepto->id], $horarioA->ciclo_id, $horarioA->grado_id, null, null);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_deudores_por_concepto_mensualidad_con_curso_paralelo_solo_incluye_a_los_asignados(): void
    {
        $curso = Curso::factory()->create();
        $horarioA = Horario::factory()->create(['curso_id' => $curso->id]);
        $horarioB = Horario::factory()->create([
            'curso_id' => $curso->id,
            'grado_id' => $horarioA->grado_id,
            'ciclo_id' => $horarioA->ciclo_id,
        ]);

        $matriculaAsignada = Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);
        $matriculaAsignada->horarios()->attach($horarioA->id);
        Cuota::factory()->create(['plan_pago_id' => PlanPago::factory()->create(['matricula_id' => $matriculaAsignada->id])->id]);

        // Matriculado en el mismo grado+ciclo, pero sin asignación a ninguno de los horarios de este curso.
        $matriculaSinAsignar = Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);
        Cuota::factory()->create(['plan_pago_id' => PlanPago::factory()->create(['matricula_id' => $matriculaSinAsignar->id])->id]);

        $concepto = ConceptoPago::factory()->create(['tipo' => TipoConceptoEnum::MENSUALIDAD]);

        $reporte = app(CobranzaService::class)->deudoresPorConceptos([$concepto->id], $horarioA->ciclo_id, $horarioA->grado_id, $curso->id, null);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_deudores_por_concepto_libre_incluye_pagos_pendientes_y_rechazados_pero_no_aprobados(): void
    {
        $concepto = ConceptoPago::factory()->create(['tipo' => TipoConceptoEnum::CERTIFICADO, 'nombre' => 'Certificado de estudios']);

        $estudiantePendiente = Estudiante::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Torres']);
        Pago::factory()->create(['estudiante_id' => $estudiantePendiente->id, 'concepto_id' => $concepto->id, 'estado' => EstadoPagoEnum::PENDIENTE]);

        $estudianteRechazado = Estudiante::factory()->create(['nombres' => 'Beto', 'apellidos' => 'Ruiz']);
        Pago::factory()->create(['estudiante_id' => $estudianteRechazado->id, 'concepto_id' => $concepto->id, 'estado' => EstadoPagoEnum::RECHAZADO]);

        $estudianteAprobado = Estudiante::factory()->create(['nombres' => 'Carla', 'apellidos' => 'Vega']);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudianteAprobado->id, 'concepto_id' => $concepto->id]);

        $reporte = app(CobranzaService::class)->deudoresPorConceptos([$concepto->id], null, null, null, null);

        $this->assertCount(2, $reporte['filas']);
        $nombres = collect($reporte['filas'])->pluck(0)->implode(',');
        $this->assertStringContainsString('Ana', $nombres);
        $this->assertStringContainsString('Beto', $nombres);
        $this->assertStringNotContainsString('Carla', $nombres);
    }

    public function test_deudores_por_conceptos_admite_mas_de_un_concepto(): void
    {
        $conceptoA = ConceptoPago::factory()->create(['tipo' => TipoConceptoEnum::CERTIFICADO, 'nombre' => 'Certificado']);
        $conceptoB = ConceptoPago::factory()->create(['tipo' => TipoConceptoEnum::CONSTANCIA, 'nombre' => 'Constancia']);

        $estudianteA = Estudiante::factory()->create();
        Pago::factory()->create(['estudiante_id' => $estudianteA->id, 'concepto_id' => $conceptoA->id, 'estado' => EstadoPagoEnum::PENDIENTE]);

        $estudianteB = Estudiante::factory()->create();
        Pago::factory()->create(['estudiante_id' => $estudianteB->id, 'concepto_id' => $conceptoB->id, 'estado' => EstadoPagoEnum::PENDIENTE]);

        $reporte = app(CobranzaService::class)->deudoresPorConceptos([$conceptoA->id, $conceptoB->id], null, null, null, null);

        $this->assertCount(2, $reporte['filas']);
        $conceptosEnFilas = collect($reporte['filas'])->pluck(3)->all();
        $this->assertContains('Certificado', $conceptosEnFilas);
        $this->assertContains('Constancia', $conceptosEnFilas);
    }

    public function test_deudores_por_conceptos_sin_datos_devuelve_lista_vacia(): void
    {
        $concepto = ConceptoPago::factory()->create(['tipo' => TipoConceptoEnum::MENSUALIDAD]);

        $reporte = app(CobranzaService::class)->deudoresPorConceptos([$concepto->id], null, null, null, null);

        $this->assertSame([], $reporte['filas']);
    }
}
