<?php

namespace Tests\Feature\Pagos;

use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\Recibo;
use App\Modules\Pagos\Services\ReciboService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReciboServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReciboService
    {
        return $this->app->make(ReciboService::class);
    }

    public function test_emitir_asigna_un_correlativo_simple_sin_prefijo_de_ano(): void
    {
        $pago = Pago::factory()->create();

        $recibo = $this->service()->emitir($pago, SerieReciboEnum::ORIGINAL);

        $this->assertSame('000001', $recibo->numero_recibo);
    }

    public function test_cada_serie_lleva_su_propio_correlativo_independiente(): void
    {
        $primero = $this->service()->emitir(Pago::factory()->create(), SerieReciboEnum::ORIGINAL);
        $segundo = $this->service()->emitir(Pago::factory()->create(), SerieReciboEnum::ORIGINAL);
        $enLaOtraSerie = $this->service()->emitir(Pago::factory()->create(), SerieReciboEnum::COPIA);

        $this->assertSame('000001', $primero->numero_recibo);
        $this->assertSame('000002', $segundo->numero_recibo);
        $this->assertSame('000001', $enLaOtraSerie->numero_recibo);
    }

    public function test_emitir_guarda_la_serie_elegida_y_genera_un_pdf(): void
    {
        $pago = Pago::factory()->create();

        $recibo = $this->service()->emitir($pago, SerieReciboEnum::COPIA);

        $this->assertSame(SerieReciboEnum::COPIA, $recibo->serie);
        $this->assertNotNull($recibo->getFirstMedia('pdf'));
    }

    public function test_el_html_del_recibo_incluye_solo_la_serie_elegida(): void
    {
        $pago = Pago::factory()->create();
        $recibo = $this->service()->emitir($pago, SerieReciboEnum::ORIGINAL);

        $html = view('pdf.recibo', ['pago' => $pago, 'recibo' => $recibo, 'serie' => SerieReciboEnum::ORIGINAL])->render();

        $this->assertStringContainsString('Recibo de pago', $html);
        $this->assertStringContainsString('001-000001', $html);
        $this->assertStringNotContainsString('002-000001', $html);
    }

    public function test_regenerar_reasigna_correlativos_por_serie_y_no_choca_con_el_unique(): void
    {
        $recibos = Recibo::factory()->count(3)->sequence(
            ['serie' => SerieReciboEnum::ORIGINAL, 'numero_recibo' => 'R-2025-000003'],
            ['serie' => SerieReciboEnum::ORIGINAL, 'numero_recibo' => 'R-2025-000001'],
            ['serie' => SerieReciboEnum::COPIA, 'numero_recibo' => 'R-2025-000002'],
        )->create();

        $this->artisan('recibos:regenerar')->assertSuccessful();

        $ordenados = $recibos->fresh();

        $this->assertSame('000001', $ordenados[0]->numero_recibo);
        $this->assertSame('000002', $ordenados[1]->numero_recibo);
        // Es el primer (y único) recibo de la serie COPIA en este set, así
        // que su correlativo arranca de nuevo en 1, no continúa el de ORIGINAL.
        $this->assertSame('000001', $ordenados[2]->numero_recibo);

        foreach ($ordenados as $recibo) {
            $this->assertNotNull($recibo->getFirstMedia('pdf'));
        }
    }
}
