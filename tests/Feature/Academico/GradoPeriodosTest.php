<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\GradoService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GradoPeriodosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_periodos_historicos_trae_los_ciclos_con_matriculas_de_ese_grado(): void
    {
        $grado = Grado::factory()->create();
        $cicloViejo = Ciclo::factory()->create(['fecha_inicio' => now()->subYear()]);
        $cicloNuevo = Ciclo::factory()->create(['fecha_inicio' => now()]);

        Matricula::factory()->count(2)->create(['grado_id' => $grado->id, 'ciclo_id' => $cicloViejo->id]);
        Matricula::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $cicloNuevo->id]);

        $periodos = $this->app->make(GradoService::class)->periodosHistoricos($grado);

        $this->assertCount(2, $periodos);
        $this->assertSame($cicloNuevo->id, $periodos->first()['ciclo']->id);
        $this->assertSame(1, $periodos->first()['matriculados']);
        $this->assertSame(2, $periodos->last()['matriculados']);
    }

    public function test_un_grado_sin_matriculas_no_tiene_periodos(): void
    {
        $grado = Grado::factory()->create();

        $periodos = $this->app->make(GradoService::class)->periodosHistoricos($grado);

        $this->assertTrue($periodos->isEmpty());
    }

    public function test_la_pantalla_muestra_los_periodos_al_expandir(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create(['nombre' => 'Grupo 1 (Enero - Junio)']);
        Matricula::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $this->actingAs($coordinador);

        Volt::test('academico.grados.index')
            ->call('alternarPeriodos', $grado->id)
            ->assertSee('Grupo 1 (Enero - Junio)')
            ->assertSee('1 matriculado');
    }
}
