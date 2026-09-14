<?php

namespace Tests\Feature\Docentes;

use App\Models\User;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Docentes\Services\DocenteService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocenteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): DocenteService
    {
        return $this->app->make(DocenteService::class);
    }

    public function test_registrar_crea_el_usuario_con_rol_docente_y_la_ficha(): void
    {
        $docente = $this->service()->registrar([
            'nombres' => 'Ana',
            'apellidos' => 'Torres Quispe',
            'dni' => new Dni('45678912'),
            'celular' => new Telefono('987654321'),
            'especialidad' => 'Matemática',
            'gradoAcademico' => 'Licenciado',
            'fechaIngreso' => '2024-03-01',
        ]);

        $this->assertSame('Ana Torres Quispe', $docente->usuario->name);
        $this->assertSame('45678912@ceba.test', $docente->usuario->email);
        $this->assertTrue($docente->usuario->hasRole(RolEnum::DOCENTE->value));
        $this->assertSame('Matemática', $docente->especialidad);
    }

    public function test_registrar_reutiliza_la_cuenta_si_el_dni_ya_existe(): void
    {
        $usuario = User::factory()->create(['dni' => '45678912']);

        $docente = $this->service()->registrar([
            'nombres' => 'Ana',
            'apellidos' => 'Torres Quispe',
            'dni' => new Dni('45678912'),
            'celular' => null,
            'especialidad' => null,
            'gradoAcademico' => null,
            'fechaIngreso' => null,
        ]);

        $this->assertSame($usuario->id, $docente->user_id);
        $this->assertTrue($usuario->fresh()->hasRole(RolEnum::DOCENTE->value));
    }

    public function test_dni_disponible_es_falso_si_ya_existe(): void
    {
        User::factory()->create(['dni' => '45678912']);

        $this->assertFalse($this->service()->dniDisponible('45678912'));
        $this->assertTrue($this->service()->dniDisponible('11112222'));
    }

    public function test_actualizar_cambia_los_campos_propios_del_docente(): void
    {
        $docente = Docente::factory()->create(['especialidad' => 'Comunicación']);

        $actualizado = $this->service()->actualizar($docente, [
            'especialidad' => 'Inglés',
            'gradoAcademico' => 'Magíster',
            'fechaIngreso' => '2020-01-01',
        ]);

        $this->assertSame('Inglés', $actualizado->especialidad);
        $this->assertSame('Magíster', $actualizado->grado_academico);
    }

    public function test_registrar_desde_filas_reporta_la_fila_con_dni_repetido(): void
    {
        User::factory()->create(['dni' => '45678912']);

        $filas = collect([
            collect(['nombres' => 'Ana', 'apellidos' => 'Torres', 'dni' => '45678912']),
            collect(['nombres' => 'Luis', 'apellidos' => 'Ramírez', 'dni' => '78912345']),
        ]);

        $resultado = $this->service()->registrarDesdeFilas($filas);

        $this->assertSame(1, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
        $this->assertSame(2, $resultado['errores'][0]['fila']);
    }

    public function test_registrar_desde_filas_exige_nombres_apellidos_y_dni(): void
    {
        $filas = collect([
            collect(['nombres' => '', 'apellidos' => 'Torres', 'dni' => '45678912']),
        ]);

        $resultado = $this->service()->registrarDesdeFilas($filas);

        $this->assertSame(0, $resultado['exitosos']);
        $this->assertCount(1, $resultado['errores']);
    }
}
