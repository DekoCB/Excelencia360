<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regresión: layouts/app.blade.php renderiza x-slot="header" dentro de un
 * <header> propio, FUERA del wire:id que Livewire usa para rastrear el
 * componente de la página (que envuelve solo {{ $slot }}, dentro de
 * <main>). Cualquier wire:click puesto en x-slot="header" queda huérfano:
 * el clic no falla con un error, simplemente no dispara ninguna petición,
 * porque Livewire no encuentra un componente ancestro al que atribuirlo.
 *
 * Esta prueba verifica, para cada página con un botón "Nuevo X", que ese
 * botón aparece después de <main> en el HTML — es decir, dentro del árbol
 * que Livewire sí rastrea — y no dentro del <header> huérfano.
 */
class HeaderActionButtonsAreInteractiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function paginasConBotonDeAccion(): array
    {
        return [
            'usuarios.index' => ['usuarios.index', RolEnum::DIRECCION->value, 'wire:click="abrirModal"'],
            'academico.grados.index' => ['academico.grados.index', RolEnum::COORDINADOR->value, 'wire:click="abrirModal"'],
            'academico.ciclos.index' => ['academico.ciclos.index', RolEnum::COORDINADOR->value, 'wire:click="abrirModal"'],
            'academico.cursos.index' => ['academico.cursos.index', RolEnum::COORDINADOR->value, 'wire:click="abrirModal"'],
            'academico.aulas.index' => ['academico.aulas.index', RolEnum::COORDINADOR->value, 'wire:click="abrirModal"'],
            'academico.horarios.index' => ['academico.horarios.index', RolEnum::COORDINADOR->value, 'wire:click="abrirModal"'],
            'pagos.conceptos' => ['pagos.conceptos', RolEnum::COORDINADOR->value, 'wire:click="abrirModal"'],
            'pagos.cuentas-bancarias' => ['pagos.cuentas-bancarias', RolEnum::TESORERIA->value, 'wire:click="abrirModal"'],
            'matricula.index' => ['matricula.index', RolEnum::COORDINADOR->value, "wire:click=\"\$set('mostrarWizard', true)\""],
        ];
    }

    #[DataProvider('paginasConBotonDeAccion')]
    public function test_el_boton_nuevo_esta_dentro_del_arbol_del_componente(string $routeName, string $rol, string $boton): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        $html = $this->actingAs($usuario)->get(route($routeName))->assertOk()->getContent();

        $posicionMain = strpos($html, '<main');
        $posicionBoton = strpos($html, $boton);

        $this->assertNotFalse($posicionMain, 'La página no tiene un <main> — no se pudo verificar el árbol del componente.');
        $this->assertNotFalse($posicionBoton, "El botón [{$boton}] no aparece en la página.");
        $this->assertGreaterThan(
            $posicionMain,
            $posicionBoton,
            "El botón [{$boton}] aparece antes de <main>, es decir, fuera del wire:id del componente — quedaría huérfano y el clic no haría nada."
        );
    }
}
