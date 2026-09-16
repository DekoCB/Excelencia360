<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Models\Pago;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MisHijosPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function apoderadoCon(Estudiante ...$hijos): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::APODERADO->value);

        foreach ($hijos as $hijo) {
            Apoderado::factory()->create(['estudiante_id' => $hijo->id, 'user_id' => $usuario->id]);
        }

        return $usuario;
    }

    public function test_un_apoderado_con_un_solo_hijo_ve_su_informacion_sin_selector(): void
    {
        $hijo = Estudiante::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Quispe Mamani']);
        $apoderado = $this->apoderadoCon($hijo);

        $this->actingAs($apoderado)
            ->get(route('matricula.mis-hijos'))
            ->assertOk()
            ->assertSee('Ana Quispe Mamani')
            ->assertDontSee('Elige a quién ver');
    }

    public function test_un_apoderado_con_dos_hijos_ve_un_selector_y_puede_cambiar_entre_ellos(): void
    {
        $hijoUno = Estudiante::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Quispe Mamani']);
        $hijoDos = Estudiante::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Quispe Mamani']);
        $apoderado = $this->apoderadoCon($hijoUno, $hijoDos);

        $this->actingAs($apoderado);

        Volt::test('matricula.mis-hijos')
            ->assertSee('Elige a quién ver')
            ->assertSee('Ana Quispe Mamani')
            ->assertSee('Luis Quispe Mamani')
            ->assertSee('Elige a uno de tus hijos')
            ->call('seleccionarHijo', $hijoDos->id)
            ->assertHasNoErrors()
            ->assertSee('DNI '.$hijoDos->dni);
    }

    public function test_un_apoderado_no_puede_ver_al_hijo_de_otro_apoderado(): void
    {
        $hijoAjeno = Estudiante::factory()->create();
        $this->apoderadoCon($hijoAjeno);

        $hijoPropio = Estudiante::factory()->create();
        $apoderado = $this->apoderadoCon($hijoPropio);

        $this->actingAs($apoderado);

        Volt::test('matricula.mis-hijos')
            ->call('seleccionarHijo', $hijoAjeno->id)
            ->assertForbidden();
    }

    public function test_manipular_la_propiedad_publica_directamente_no_filtra_al_hijo_de_otro(): void
    {
        // Livewire expone estudianteSeleccionadoId al cliente: forzarlo con
        // set() en vez de pasar por seleccionarHijo() simula a alguien
        // manipulando la petición a mano. with() debe descartarlo igual.
        $hijoAjeno = Estudiante::factory()->create(['nombres' => 'Secreto', 'apellidos' => 'Ajeno']);
        $this->apoderadoCon($hijoAjeno);

        $hijoPropio = Estudiante::factory()->create();
        $apoderado = $this->apoderadoCon($hijoPropio);

        $this->actingAs($apoderado);

        Volt::test('matricula.mis-hijos')
            ->set('estudianteSeleccionadoId', $hijoAjeno->id)
            ->assertDontSee('Secreto Ajeno')
            ->assertSee('Elige a uno de tus hijos');
    }

    public function test_exportar_pdf_de_un_hijo_ajeno_es_rechazado(): void
    {
        $hijoAjeno = Estudiante::factory()->create();
        $this->apoderadoCon($hijoAjeno);

        $hijoPropio = Estudiante::factory()->create();
        $apoderado = $this->apoderadoCon($hijoPropio);

        $this->actingAs($apoderado);

        Volt::test('matricula.mis-hijos')
            ->set('estudianteSeleccionadoId', $hijoAjeno->id)
            ->call('exportarPdf')
            ->assertForbidden();
    }

    public function test_un_apoderado_puede_exportar_el_pdf_de_su_propio_hijo(): void
    {
        $hijo = Estudiante::factory()->create();
        Pago::factory()->aprobado()->create(['estudiante_id' => $hijo->id, 'monto' => 80]);
        $apoderado = $this->apoderadoCon($hijo);

        $this->actingAs($apoderado);

        $testable = Volt::test('matricula.mis-hijos')->call('exportarPdf');

        $this->assertArrayHasKey('download', $testable->effects);
        $this->assertSame('application/pdf', $testable->effects['download']['contentType']);
    }

    public function test_un_apoderado_sin_hijos_vinculados_no_puede_entrar(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::APODERADO->value);

        $this->actingAs($usuario)
            ->get(route('matricula.mis-hijos'))
            ->assertForbidden();
    }

    public function test_un_docente_no_puede_ver_mis_hijos(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('matricula.mis-hijos'))
            ->assertForbidden();
    }

    public function test_el_enlace_mis_hijos_aparece_en_el_menu_para_apoderado(): void
    {
        $hijo = Estudiante::factory()->create();
        $apoderado = $this->apoderadoCon($hijo);

        $this->actingAs($apoderado)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tutores/Apoderados');
    }

    public function test_el_enlace_mis_hijos_no_aparece_en_el_menu_para_docente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Tutores/Apoderados');
    }

    public function test_direccion_entra_sin_hijos_propios_y_ve_el_buscador(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion)
            ->get(route('matricula.mis-hijos'))
            ->assertOk()
            ->assertSee('Buscar tutor/apoderado o estudiante');
    }

    public function test_direccion_puede_buscar_y_ver_a_cualquier_hijo_por_su_apoderado(): void
    {
        $hijo = Estudiante::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Quispe Mamani']);
        Apoderado::factory()->create(['estudiante_id' => $hijo->id, 'nombres' => 'Rosa Mamani', 'user_id' => null]);

        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion);

        Volt::test('matricula.mis-hijos')
            ->set('terminoBusqueda', 'Rosa Mamani')
            ->assertSee('Ana Quispe Mamani')
            ->call('seleccionarHijo', $hijo->id)
            ->assertHasNoErrors()
            ->assertSee('DNI '.$hijo->dni);
    }

    public function test_coordinador_tambien_entra_al_directorio(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('matricula.mis-hijos'))
            ->assertOk()
            ->assertSee('Buscar tutor/apoderado o estudiante');
    }

    public function test_administrativo_sin_hijos_propios_no_entra(): void
    {
        // Administrativo tiene matricula.ver pero no reportes.historial_estudiante
        // (ni matricula.ver_propio_hijo): no debe ganar acceso nuevo al directorio.
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get(route('matricula.mis-hijos'))
            ->assertForbidden();
    }
}
