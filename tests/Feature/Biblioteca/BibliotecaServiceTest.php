<?php

namespace Tests\Feature\Biblioteca;

use App\Models\User;
use App\Modules\Biblioteca\Enums\EstadoEjemplarEnum;
use App\Modules\Biblioteca\Enums\EstadoPrestamoEnum;
use App\Modules\Biblioteca\Models\Ejemplar;
use App\Modules\Biblioteca\Models\Libro;
use App\Modules\Biblioteca\Models\Prestamo;
use App\Modules\Biblioteca\Services\BibliotecaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BibliotecaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BibliotecaService
    {
        return $this->app->make(BibliotecaService::class);
    }

    public function test_catalogo_filtra_por_titulo_autor_o_isbn(): void
    {
        Libro::factory()->create(['titulo' => 'Cien años de soledad', 'autor' => 'García Márquez', 'isbn' => '9780307474728']);
        Libro::factory()->create(['titulo' => 'Otro libro', 'autor' => 'Otro autor', 'isbn' => '1112223334445']);

        $service = $this->service();

        $this->assertCount(1, $service->catalogo('Cien años'));
        $this->assertCount(1, $service->catalogo('García Márquez'));
        $this->assertCount(1, $service->catalogo('9780307474728'));
        $this->assertCount(2, $service->catalogo());
    }

    public function test_registrar_libro_lo_crea(): void
    {
        $libro = $this->service()->registrarLibro('Título', 'Autor', '123', 'Categoría', 'Editorial', 2020);

        $this->assertDatabaseHas('libros', ['titulo' => 'Título', 'autor' => 'Autor', 'anio_publicacion' => 2020]);
        $this->assertSame('Título', $libro->titulo);
    }

    public function test_agregar_ejemplar_queda_disponible(): void
    {
        $libro = Libro::factory()->create();

        $ejemplar = $this->service()->agregarEjemplar($libro, 'BIB-00001');

        $this->assertSame(EstadoEjemplarEnum::DISPONIBLE, $ejemplar->estado);
        $this->assertSame($libro->id, $ejemplar->libro_id);
    }

    public function test_prestar_marca_el_ejemplar_como_prestado_y_crea_el_registro(): void
    {
        $ejemplar = Ejemplar::factory()->create();
        $solicitante = User::factory()->create();
        $bibliotecario = User::factory()->create();

        $prestamo = $this->service()->prestar($ejemplar, $solicitante, $bibliotecario);

        $this->assertSame(EstadoPrestamoEnum::PRESTADO, $prestamo->estado);
        $this->assertSame($solicitante->id, $prestamo->solicitante_id);
        $this->assertSame($bibliotecario->id, $prestamo->entregado_por);
        $this->assertSame(EstadoEjemplarEnum::PRESTADO, $ejemplar->fresh()->estado);
    }

    public function test_prestar_un_ejemplar_no_disponible_lanza_excepcion(): void
    {
        $ejemplar = Ejemplar::factory()->conEstado(EstadoEjemplarEnum::PRESTADO)->create();

        $this->expectException(ValidationException::class);

        $this->service()->prestar($ejemplar, User::factory()->create(), User::factory()->create());
    }

    public function test_devolver_libera_el_ejemplar(): void
    {
        $ejemplar = Ejemplar::factory()->conEstado(EstadoEjemplarEnum::PRESTADO)->create();
        $prestamo = Prestamo::factory()->for($ejemplar)->create();

        $actualizado = $this->service()->devolver($prestamo);

        $this->assertSame(EstadoPrestamoEnum::DEVUELTO, $actualizado->estado);
        $this->assertNotNull($actualizado->fecha_devolucion_real);
        $this->assertSame(EstadoEjemplarEnum::DISPONIBLE, $ejemplar->fresh()->estado);
    }

    public function test_devolver_un_prestamo_ya_cerrado_lanza_excepcion(): void
    {
        $prestamo = Prestamo::factory()->conEstado(EstadoPrestamoEnum::DEVUELTO)->create();

        $this->expectException(ValidationException::class);

        $this->service()->devolver($prestamo);
    }

    public function test_marcar_perdido_deja_el_ejemplar_perdido(): void
    {
        $ejemplar = Ejemplar::factory()->conEstado(EstadoEjemplarEnum::PRESTADO)->create();
        $prestamo = Prestamo::factory()->for($ejemplar)->create();

        $actualizado = $this->service()->marcarPerdido($prestamo);

        $this->assertSame(EstadoPrestamoEnum::PERDIDO, $actualizado->estado);
        $this->assertSame(EstadoEjemplarEnum::PERDIDO, $ejemplar->fresh()->estado);
    }

    public function test_prestamos_activos_solo_trae_los_prestados(): void
    {
        Prestamo::factory()->conEstado(EstadoPrestamoEnum::PRESTADO)->create();
        Prestamo::factory()->conEstado(EstadoPrestamoEnum::DEVUELTO)->create();
        Prestamo::factory()->conEstado(EstadoPrestamoEnum::PERDIDO)->create();

        $activos = $this->service()->prestamosActivos();

        $this->assertCount(1, $activos);
        $this->assertSame(EstadoPrestamoEnum::PRESTADO, $activos->first()->estado);
    }

    public function test_esta_vencido_es_true_solo_si_sigue_prestado_y_paso_la_fecha(): void
    {
        $vencido = Prestamo::factory()->conEstado(EstadoPrestamoEnum::PRESTADO)->create(['fecha_devolucion_esperada' => now()->subDays(3)->format('Y-m-d')]);
        $noVencidoAunPrestado = Prestamo::factory()->conEstado(EstadoPrestamoEnum::PRESTADO)->create(['fecha_devolucion_esperada' => now()->addDays(3)->format('Y-m-d')]);
        $devueltoTarde = Prestamo::factory()->conEstado(EstadoPrestamoEnum::DEVUELTO)->create(['fecha_devolucion_esperada' => now()->subDays(3)->format('Y-m-d')]);

        $this->assertTrue($vencido->estaVencido());
        $this->assertFalse($noVencidoAunPrestado->estaVencido());
        $this->assertFalse($devueltoTarde->estaVencido());
    }

    public function test_mis_prestamos_solo_trae_los_del_solicitante(): void
    {
        $solicitante = User::factory()->create();
        $otro = User::factory()->create();

        Prestamo::factory()->create(['solicitante_id' => $solicitante->id]);
        Prestamo::factory()->create(['solicitante_id' => $otro->id]);

        $resultado = $this->service()->misPrestamos($solicitante);

        $this->assertCount(1, $resultado);
        $this->assertSame($solicitante->id, $resultado->first()->solicitante_id);
    }
}
