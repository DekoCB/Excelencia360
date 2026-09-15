<?php

namespace Tests\Feature\AsistenciaDocentes;

use App\Models\User;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\AsistenciaDocentes\Models\AsistenciaDocente;
use App\Modules\AsistenciaDocentes\Services\AsistenciaDocenteService;
use App\Modules\Docentes\Models\Docente;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsistenciaDocenteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): AsistenciaDocenteService
    {
        return $this->app->make(AsistenciaDocenteService::class);
    }

    public function test_docentes_activos_devuelve_todos_ordenados_por_nombre(): void
    {
        Docente::factory()->create(['user_id' => User::factory()->create(['name' => 'Zoraida Quispe'])->id]);
        Docente::factory()->create(['user_id' => User::factory()->create(['name' => 'Ana Mamani'])->id]);

        $docentes = $this->service()->docentesActivos();

        $this->assertSame(['Ana Mamani', 'Zoraida Quispe'], $docentes->map(fn (Docente $d) => $d->usuario->name)->all());
    }

    public function test_registrar_crea_un_registro_por_docente_para_la_fecha(): void
    {
        $registrador = User::factory()->create();
        $docente1 = Docente::factory()->create();
        $docente2 = Docente::factory()->create();

        $this->service()->registrar($registrador, '2026-09-10', [
            $docente1->id => EstadoAsistenciaEnum::PRESENTE->value,
            $docente2->id => EstadoAsistenciaEnum::TARDANZA->value,
        ], [
            $docente2->id => 'Llegó 20 minutos tarde',
        ]);

        $this->assertDatabaseHas('asistencias_docentes', [
            'docente_id' => $docente1->id,
            'fecha' => '2026-09-10',
            'estado' => 'presente',
            'registrado_por' => $registrador->id,
        ]);
        $this->assertDatabaseHas('asistencias_docentes', [
            'docente_id' => $docente2->id,
            'fecha' => '2026-09-10',
            'estado' => 'tardanza',
            'observacion' => 'Llegó 20 minutos tarde',
        ]);
    }

    public function test_registrar_es_update_or_create_no_duplica_si_se_llama_dos_veces_la_misma_fecha(): void
    {
        $registrador = User::factory()->create();
        $docente = Docente::factory()->create();

        $this->service()->registrar($registrador, '2026-09-10', [$docente->id => EstadoAsistenciaEnum::PRESENTE->value]);
        $this->service()->registrar($registrador, '2026-09-10', [$docente->id => EstadoAsistenciaEnum::TARDANZA->value]);

        $this->assertDatabaseCount('asistencias_docentes', 1);
        $this->assertDatabaseHas('asistencias_docentes', ['docente_id' => $docente->id, 'estado' => 'tardanza']);
    }

    public function test_registrar_adjunta_el_justificante_cuando_se_sube_uno(): void
    {
        Storage::fake('public');

        $registrador = User::factory()->create();
        $docente = Docente::factory()->create();
        $archivo = UploadedFile::fake()->create('justificacion.pdf', 100, 'application/pdf');

        $this->service()->registrar(
            $registrador,
            '2026-09-10',
            [$docente->id => EstadoAsistenciaEnum::JUSTIFICADO->value],
            [$docente->id => 'Cita médica'],
            [$docente->id => $archivo],
        );

        $asistencia = AsistenciaDocente::query()->where('docente_id', $docente->id)->firstOrFail();
        $this->assertCount(1, $asistencia->getMedia('justificante'));
    }

    public function test_de_dia_devuelve_los_registros_de_esa_fecha_indexados_por_docente(): void
    {
        $registrador = User::factory()->create();
        $docente = Docente::factory()->create();

        $this->service()->registrar($registrador, '2026-09-10', [$docente->id => EstadoAsistenciaEnum::FALTA->value]);
        $this->service()->registrar($registrador, '2026-09-11', [$docente->id => EstadoAsistenciaEnum::PRESENTE->value]);

        $registrosDelDia = $this->service()->deDia('2026-09-10');

        $this->assertCount(1, $registrosDelDia);
        $this->assertSame(EstadoAsistenciaEnum::FALTA, $registrosDelDia->get($docente->id)->estado);
    }

    public function test_historial_docente_solo_trae_los_ultimos_n_meses(): void
    {
        $docente = Docente::factory()->create();

        AsistenciaDocente::factory()->for($docente, 'docente')->create(['fecha' => now()->subMonths(1)->format('Y-m-d')]);
        AsistenciaDocente::factory()->for($docente, 'docente')->create(['fecha' => now()->subMonths(6)->format('Y-m-d')]);

        $historial = $this->service()->historialDocente($docente, meses: 3);

        $this->assertCount(1, $historial);
    }

    public function test_resumen_docente_calcula_porcentaje_y_conteo_por_estado(): void
    {
        $docente = Docente::factory()->create();

        AsistenciaDocente::factory()->for($docente, 'docente')->conEstado(EstadoAsistenciaEnum::PRESENTE)->create(['fecha' => now()->subDays(1)->format('Y-m-d')]);
        AsistenciaDocente::factory()->for($docente, 'docente')->conEstado(EstadoAsistenciaEnum::PRESENTE)->create(['fecha' => now()->subDays(2)->format('Y-m-d')]);
        AsistenciaDocente::factory()->for($docente, 'docente')->conEstado(EstadoAsistenciaEnum::TARDANZA)->create(['fecha' => now()->subDays(3)->format('Y-m-d')]);
        AsistenciaDocente::factory()->for($docente, 'docente')->conEstado(EstadoAsistenciaEnum::FALTA)->create(['fecha' => now()->subDays(4)->format('Y-m-d')]);

        $resumen = $this->service()->resumenDocente($docente);

        $this->assertSame(4, $resumen['total']);
        $this->assertSame(3, $resumen['asistio']);
        $this->assertSame(75.0, $resumen['porcentaje']);
        $this->assertSame(2, $resumen['por_estado']['presente']);
        $this->assertSame(1, $resumen['por_estado']['tardanza']);
        $this->assertSame(1, $resumen['por_estado']['falta']);
        $this->assertSame(0, $resumen['por_estado']['justificado']);
    }
}
