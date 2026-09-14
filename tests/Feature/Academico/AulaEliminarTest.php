<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Services\AulaService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AulaEliminarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): AulaService
    {
        return $this->app->make(AulaService::class);
    }

    public function test_elimina_un_aula_sin_horarios(): void
    {
        $aula = Aula::factory()->create();

        $this->service()->eliminar($aula);

        $this->assertDatabaseMissing('aulas', ['id' => $aula->id]);
    }

    public function test_no_permite_eliminar_un_aula_con_horarios(): void
    {
        $aula = Aula::factory()->create();
        Horario::factory()->create(['aula_id' => $aula->id]);

        $this->expectException(ValidationException::class);

        $this->service()->eliminar($aula);

        $this->assertDatabaseHas('aulas', ['id' => $aula->id]);
    }

    public function test_el_coordinador_puede_eliminar_un_aula_sin_horarios_desde_la_ui(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $aula = Aula::factory()->create(['nombre' => 'Aula de prueba']);

        $this->actingAs($coordinador);

        Volt::test('academico.aulas.index')
            ->call('eliminar', $aula->id)
            ->assertSee('Aula eliminada correctamente');

        $this->assertDatabaseMissing('aulas', ['id' => $aula->id]);
    }

    public function test_eliminar_un_aula_con_horarios_desde_la_ui_muestra_un_error_claro(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $aula = Aula::factory()->create(['nombre' => 'Aula con horario']);
        Horario::factory()->create(['aula_id' => $aula->id]);

        $this->actingAs($coordinador);

        Volt::test('academico.aulas.index')
            ->call('eliminar', $aula->id)
            ->assertSee('No se puede eliminar');

        $this->assertDatabaseHas('aulas', ['id' => $aula->id]);
    }
}
