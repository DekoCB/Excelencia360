<?php

namespace Tests\Feature\Personal;

use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Personal\Models\Personal;
use App\Modules\Personal\Services\PersonalService;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): PersonalService
    {
        return $this->app->make(PersonalService::class);
    }

    public function test_registrar_crea_la_ficha_activa(): void
    {
        $persona = $this->service()->registrar([
            'nombres' => 'Rosa',
            'apellidos' => 'Mendoza Díaz',
            'dni' => new Dni('41234567'),
            'celular' => new Telefono('987654321'),
            'cargo' => 'Psicóloga',
            'area' => 'Bienestar Estudiantil',
            'fechaIngreso' => '2024-03-01',
        ]);

        $this->assertSame('Rosa Mendoza Díaz', $persona->nombreCompleto());
        $this->assertSame('41234567', $persona->dni);
        $this->assertSame('987654321', $persona->celular);
        $this->assertTrue($persona->activo);
    }

    public function test_dni_disponible_es_falso_si_ya_existe(): void
    {
        Personal::factory()->create(['dni' => '41234567']);

        $this->assertFalse($this->service()->dniDisponible('41234567'));
        $this->assertTrue($this->service()->dniDisponible('11112222'));
    }

    public function test_dni_disponible_ignora_el_registro_actual_al_editar(): void
    {
        $persona = Personal::factory()->create(['dni' => '41234567']);

        $this->assertTrue($this->service()->dniDisponible('41234567', $persona->id));
    }

    public function test_actualizar_cambia_los_campos_y_puede_desactivar(): void
    {
        $persona = Personal::factory()->create(['cargo' => 'Portero', 'activo' => true]);

        $actualizado = $this->service()->actualizar($persona, [
            'nombres' => $persona->nombres,
            'apellidos' => $persona->apellidos,
            'celular' => null,
            'cargo' => 'Vigilante nocturno',
            'area' => null,
            'fechaIngreso' => null,
            'activo' => false,
        ]);

        $this->assertSame('Vigilante nocturno', $actualizado->cargo);
        $this->assertFalse($actualizado->activo);
    }

    public function test_registrar_desde_filas_reporta_la_fila_con_dni_repetido(): void
    {
        Personal::factory()->create(['dni' => '41234567']);

        $filas = collect([
            collect(['nombres' => 'Rosa', 'apellidos' => 'Mendoza', 'dni' => '41234567', 'cargo' => 'Psicóloga']),
            collect(['nombres' => 'Jorge', 'apellidos' => 'Salazar', 'dni' => '71234567', 'cargo' => 'Portero']),
        ]);

        $resultado = $this->service()->registrarDesdeFilas($filas);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame(2, $resultado['errores'][0]['fila']);
    }

    public function test_registrar_desde_filas_exige_nombres_apellidos_dni_y_cargo(): void
    {
        $filas = collect([
            collect(['nombres' => '', 'apellidos' => 'Salazar', 'dni' => '71234567', 'cargo' => 'Portero']),
        ]);

        $resultado = $this->service()->registrarDesdeFilas($filas);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
    }
}
