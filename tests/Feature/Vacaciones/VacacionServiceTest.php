<?php

namespace Tests\Feature\Vacaciones;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Vacaciones\Services\VacacionService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VacacionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): VacacionService
    {
        return $this->app->make(VacacionService::class);
    }

    private function docente(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RolEnum::DOCENTE->value);

        return $user;
    }

    public function test_activar_registra_vacaciones_con_fechas_libres(): void
    {
        $docente = $this->docente();

        $vacacion = $this->service()->activar($docente, '2026-06-01', '2026-06-20', null);

        $this->assertSame('2026-06-01', $vacacion->fecha_inicio->format('Y-m-d'));
        $this->assertSame('2026-06-20', $vacacion->fecha_fin->format('Y-m-d'));
        $this->assertSame($docente->id, $vacacion->user_id);
    }

    public function test_activar_rechaza_fecha_fin_anterior_a_fecha_inicio(): void
    {
        $docente = $this->docente();

        $this->expectException(ValidationException::class);

        $this->service()->activar($docente, '2026-06-20', '2026-06-01', null);
    }

    public function test_vigentes_y_historial_particionan_por_fecha_fin(): void
    {
        $enVacaciones = $this->docente();
        $yaTermino = $this->docente();

        $this->service()->activar($enVacaciones, now()->subDays(10)->format('Y-m-d'), now()->addDays(10)->format('Y-m-d'), null);
        $this->service()->activar($yaTermino, now()->subMonths(4)->format('Y-m-d'), now()->subMonths(3)->format('Y-m-d'), null);

        $vigentes = $this->service()->vigentes();
        $historial = $this->service()->historial();

        $this->assertCount(1, $vigentes);
        $this->assertSame($enVacaciones->id, $vigentes->first()->user_id);
        $this->assertCount(1, $historial);
        $this->assertSame($yaTermino->id, $historial->first()->user_id);
    }
}
