<?php

namespace Tests\Feature\Biblioteca;

use App\Models\User;
use App\Modules\Biblioteca\Models\Ejemplar;
use App\Modules\Biblioteca\Models\Libro;
use App\Modules\Biblioteca\Models\Prestamo;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BibliotecaPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function rolesConVer(): array
    {
        return [
            [RolEnum::DIRECCION->value],
            [RolEnum::COORDINADOR->value],
            [RolEnum::ADMINISTRATIVO->value],
            [RolEnum::TESORERIA->value],
            [RolEnum::DOCENTE->value],
            [RolEnum::ESTUDIANTE->value],
        ];
    }

    /**
     * @dataProvider rolesConVer
     */
    public function test_roles_con_ver_pueden_entrar_al_catalogo(string $rol): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        $this->actingAs($usuario)
            ->get(route('biblioteca.index'))
            ->assertOk();
    }

    public function test_apoderado_no_puede_entrar_al_catalogo(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::APODERADO->value);

        $this->actingAs($usuario)
            ->get(route('biblioteca.index'))
            ->assertForbidden();
    }

    public function test_coordinador_puede_registrar_libro_ejemplar_y_prestamo(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $solicitante = User::factory()->create(['dni' => '99988877']);
        $solicitante->assignRole(RolEnum::ESTUDIANTE->value);

        $this->actingAs($coordinador);

        $component = Volt::test('biblioteca.index')
            ->call('abrirFormLibro')
            ->set('titulo', 'Libro de prueba')
            ->set('autor', 'Autor de prueba')
            ->call('guardarLibro')
            ->assertHasNoErrors();

        $libro = Libro::query()->where('titulo', 'Libro de prueba')->firstOrFail();

        $component->call('abrirFormEjemplar', $libro->id)
            ->set('codigoInventario', 'BIB-99999')
            ->call('guardarEjemplar')
            ->assertHasNoErrors();

        $ejemplar = Ejemplar::query()->where('codigo_inventario', 'BIB-99999')->firstOrFail();

        $component->call('abrirFormPrestamo', $ejemplar->id)
            ->set('dniSolicitante', '99988877')
            ->call('guardarPrestamo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('prestamos', [
            'ejemplar_id' => $ejemplar->id,
            'solicitante_id' => $solicitante->id,
            'entregado_por' => $coordinador->id,
        ]);
    }

    public function test_un_docente_no_puede_registrar_libro_directamente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $this->actingAs($docente);

        Volt::test('biblioteca.index')
            ->set('titulo', 'Intento no autorizado')
            ->set('autor', 'X')
            ->call('guardarLibro')
            ->assertForbidden();
    }

    public function test_un_estudiante_no_puede_devolver_un_prestamo_directamente(): void
    {
        $prestamo = Prestamo::factory()->create();

        $estudiante = User::factory()->create();
        $estudiante->assignRole(RolEnum::ESTUDIANTE->value);
        $this->actingAs($estudiante);

        Volt::test('biblioteca.index')
            ->call('devolver', $prestamo->id)
            ->assertForbidden();
    }

    public function test_un_docente_ve_su_propio_historial_de_prestamos(): void
    {
        $docenteUsuario = User::factory()->create(['name' => 'Docente Con Prestamo']);
        $docenteUsuario->assignRole(RolEnum::DOCENTE->value);

        $libro = Libro::factory()->create(['titulo' => 'Libro Prestado Al Docente']);
        $ejemplar = Ejemplar::factory()->for($libro)->create();
        Prestamo::factory()->create(['ejemplar_id' => $ejemplar->id, 'solicitante_id' => $docenteUsuario->id]);

        $this->actingAs($docenteUsuario)
            ->get(route('biblioteca.mis-prestamos'))
            ->assertOk()
            ->assertSee('Libro Prestado Al Docente');
    }

    public function test_coordinador_no_tiene_ver_propio_y_no_puede_entrar_a_mis_prestamos(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('biblioteca.mis-prestamos'))
            ->assertForbidden();
    }

    public function test_el_enlace_biblioteca_aparece_en_el_menu_para_coordinador(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Biblioteca');
    }
}
