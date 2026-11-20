<?php

namespace Tests\Feature\Academico;

use App\Modules\Academico\Services\CicloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CicloValidacionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CicloService
    {
        return $this->app->make(CicloService::class);
    }

    public function test_un_ciclo_con_fechas_libres_se_crea_sin_problema(): void
    {
        $ciclo = $this->service()->crear([
            'nombre' => '2026-1',
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);

        $this->assertDatabaseHas('ciclos', ['id' => $ciclo->id, 'nombre' => '2026-1']);
    }

    public function test_la_fecha_fin_debe_ser_posterior_a_la_de_inicio(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->crear([
            'nombre' => '2026-1',
            'anio' => 2026,
            'fecha_inicio' => '2026-06-30',
            'fecha_fin' => '2026-01-01',
        ]);
    }

    public function test_dos_periodos_pueden_solaparse_libremente_en_fechas(): void
    {
        // Ej. "2026-0" acelerado corriendo en paralelo a "2026-1" -- ya no
        // hay un concepto de "mismo tipo"/"misma modalidad" que lo impida.
        $this->service()->crear([
            'nombre' => '2026-1',
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);

        $ciclo = $this->service()->crear([
            'nombre' => '2026-0',
            'anio' => 2026,
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-05-31',
        ]);

        $this->assertDatabaseHas('ciclos', ['id' => $ciclo->id]);
    }
}
