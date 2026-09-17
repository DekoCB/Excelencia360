<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\Siagie;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Events\EstudianteMatriculado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MatriculaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): MatriculaService
    {
        return $this->app->make(MatriculaService::class);
    }

    private function datosEstudianteMayor(): RegistrarEstudianteData
    {
        return new RegistrarEstudianteData(
            nombres: 'Ana',
            apellidos: 'García Pérez',
            dni: new Dni('12345678'),
            fechaNacimiento: now()->subYears(30)->format('Y-m-d'),
            estadoCivil: null,
            direccion: 'Av. Siempre Viva 123',
            celular: new Telefono('987654321'),
            observaciones: null,
        );
    }

    private function datosEstudianteMenor(): RegistrarEstudianteData
    {
        return new RegistrarEstudianteData(
            nombres: 'Diego',
            apellidos: 'Torres Huamán',
            dni: new Dni('78912345'),
            fechaNacimiento: now()->subYears(15)->format('Y-m-d'),
            estadoCivil: null,
            direccion: 'Av. Las Flores 512',
            celular: null,
            observaciones: null,
        );
    }

    private function cicloConPeriodoAbierto(): Ciclo
    {
        $ciclo = Ciclo::factory()->activo()->create([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ]);

        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(10),
        ]);

        return $ciclo;
    }

    public function test_calcula_correctamente_si_es_menor_de_edad(): void
    {
        $this->assertTrue(MatriculaService::esMenorDeEdad(now()->subYears(15)->format('Y-m-d')));
        $this->assertFalse(MatriculaService::esMenorDeEdad(now()->subYears(25)->format('Y-m-d')));
    }

    public function test_registra_un_estudiante_correctamente(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());

        $this->assertDatabaseHas('estudiantes', ['dni' => '12345678', 'es_menor_edad' => false]);
        $this->assertSame('Ana García Pérez', $estudiante->nombreCompleto());
    }

    public function test_registrar_estudiante_le_crea_su_cuenta_de_acceso_con_correo_y_password_autoasignados(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());

        $this->assertSame('12345678@ceba.test', $estudiante->email);
        $this->assertNotNull($estudiante->user_id);

        $usuario = $estudiante->user;
        $this->assertSame('12345678@ceba.test', $usuario->email);
        $this->assertSame('12345678', $usuario->dni);
        $this->assertTrue($usuario->hasRole(RolEnum::ESTUDIANTE->value));
        $this->assertTrue(Hash::check('12345678', $usuario->password));
    }

    public function test_registrar_estudiante_reutiliza_una_cuenta_existente_con_el_mismo_dni(): void
    {
        $usuarioExistente = User::factory()->create(['dni' => '12345678']);

        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());

        $this->assertSame($usuarioExistente->id, $estudiante->user_id);
        $this->assertTrue($usuarioExistente->fresh()->hasRole(RolEnum::ESTUDIANTE->value));
    }

    public function test_no_permite_registrar_apoderado_para_estudiante_mayor_de_edad(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());

        $this->expectException(ValidationException::class);

        $this->service()->registrarApoderado($estudiante, new RegistrarApoderadoData(
            nombres: 'Pedro García',
            dni: new Dni('87654321'),
            celular: new Telefono('912345678'),
            correo: null,
            direccion: null,
            parentesco: 'Padre',
        ));
    }

    public function test_registrar_apoderado_le_crea_acceso_al_portal(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMenor());

        $apoderado = $this->service()->registrarApoderado($estudiante, new RegistrarApoderadoData(
            nombres: 'Pedro García',
            dni: new Dni('87654321'),
            celular: new Telefono('912345678'),
            correo: null,
            direccion: null,
            parentesco: 'Padre',
        ));

        $this->assertNotNull($apoderado->user_id);

        $usuario = User::query()->findOrFail($apoderado->user_id);
        $this->assertSame('87654321@ceba.test', $usuario->email);
        $this->assertTrue($usuario->hasRole(RolEnum::APODERADO->value));
        $this->assertTrue(Hash::check('87654321', $usuario->password));
    }

    public function test_registrar_apoderado_con_dni_ya_registrado_reutiliza_la_cuenta_para_ambos_hijos(): void
    {
        // Los dos hermanos comparten apoderado (mismo DNI): debe quedar
        // una sola cuenta viendo a los dos, no una por cada fila de
        // Apoderado (estudiante_id es unique en la tabla).
        $usuarioExistente = User::factory()->create(['dni' => '87654321']);
        $usuarioExistente->assignRole(RolEnum::APODERADO->value);

        $hijoUno = $this->service()->registrarEstudiante($this->datosEstudianteMenor());
        $hijoDos = $this->service()->registrarEstudiante(new RegistrarEstudianteData(
            nombres: 'Rosa',
            apellidos: 'Torres Huamán',
            dni: new Dni('78912346'),
            fechaNacimiento: now()->subYears(13)->format('Y-m-d'),
            estadoCivil: null,
            direccion: 'Av. Las Flores 512',
            celular: null,
            observaciones: null,
        ));

        $apoderadoUno = $this->service()->registrarApoderado($hijoUno, new RegistrarApoderadoData(
            nombres: 'Pedro García', dni: new Dni('87654321'), celular: new Telefono('912345678'),
            correo: null, direccion: null, parentesco: 'Padre',
        ));
        $apoderadoDos = $this->service()->registrarApoderado($hijoDos, new RegistrarApoderadoData(
            nombres: 'Pedro García', dni: new Dni('87654321'), celular: new Telefono('912345678'),
            correo: null, direccion: null, parentesco: 'Padre',
        ));

        $this->assertSame($usuarioExistente->id, $apoderadoUno->user_id);
        $this->assertSame($usuarioExistente->id, $apoderadoDos->user_id);
        $this->assertNotSame($apoderadoUno->estudiante_id, $apoderadoDos->estudiante_id);
    }

    public function test_no_permite_matricular_sin_periodo_de_matricula_abierto(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service()->matricular($estudiante, new RegistrarMatriculaData(
            cicloId: $ciclo->id,
            gradoId: $grado->id,
            observaciones: null,
            registradoPor: null,
        ));
    }

    public function test_no_permite_matricula_duplicada_para_el_mismo_estudiante_y_ciclo(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $data = new RegistrarMatriculaData($ciclo->id, $grado->id, null, null);

        $this->service()->matricular($estudiante, $data);

        $this->expectException(ValidationException::class);

        $this->service()->matricular($estudiante, $data);
    }

    public function test_matricular_dispara_el_evento_estudiante_matriculado(): void
    {
        Event::fake([EstudianteMatriculado::class]);

        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        Event::assertDispatched(EstudianteMatriculado::class);
    }

    public function test_matricular_guarda_el_siagie_elegido_a_mano(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        $siagie = Siagie::factory()->create(['tipo' => TipoSiagieEnum::SEGUNDO, 'anio' => $ciclo->anio]);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData(
            cicloId: $ciclo->id,
            gradoId: $grado->id,
            observaciones: null,
            registradoPor: null,
            siagieId: $siagie->id,
        ));

        $this->assertSame($siagie->id, $matricula->fresh()->siagie_id);
        $this->assertSame("{$ciclo->anio}-2", $matricula->siagieCompleto());
    }

    public function test_matricular_sin_elegir_siagie_lo_deja_nulo(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertNull($matricula->siagie_id);
        $this->assertNull($matricula->siagieCompleto());
    }

    public function test_matricular_actualiza_el_grado_actual_del_estudiante(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertSame($grado->id, $estudiante->fresh()->grado_actual_id);
    }

    public function test_matricular_en_un_grado_sin_horarios_deja_la_matricula_sin_horarios_asignados(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertTrue($matricula->horarios->isEmpty());
    }

    public function test_matricular_calcula_fecha_fin_estudio_a_seis_meses_para_mayor_de_edad(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertTrue($matricula->fecha_matricula->addMonths(6)->isSameDay($matricula->fecha_fin_estudio));
    }

    public function test_matricular_calcula_fecha_fin_estudio_a_ocho_meses_para_menor_de_edad(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMenor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertTrue($matricula->fecha_matricula->addMonths(8)->isSameDay($matricula->fecha_fin_estudio));
    }

    public function test_matricular_en_un_ciclo_anual_no_exige_periodo_de_matricula_abierto(): void
    {
        // A diferencia de los Grupos de 6 meses, SIAGIE anual no depende de
        // un PeriodoMatricula: este ciclo no tiene ninguno y aun así debe
        // poder matricularse.
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = Ciclo::factory()->anual()->activo()->create();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertTrue($ciclo->fecha_fin->isSameDay($matricula->fecha_fin_estudio));
    }

    public function test_reasignar_fecha_fin_estudio_la_actualiza(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $nuevaFecha = now()->addMonths(9)->format('Y-m-d');
        $matricula = $this->service()->reasignarFechaFinEstudio($matricula, $nuevaFecha);

        $this->assertSame($nuevaFecha, $matricula->fecha_fin_estudio->format('Y-m-d'));
        $this->assertSame($nuevaFecha, $matricula->fresh()->fecha_fin_estudio->format('Y-m-d'));
    }

    public function test_asignar_horario_de_curso_agrega_el_horario_a_la_matricula(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $horarioDos = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $matricula = $this->service()->asignarHorarioDeCurso($matricula, $horarioDos->curso_id, $horarioDos->id);

        $this->assertTrue($matricula->horarios->pluck('id')->contains($horarioDos->id));
        $this->assertTrue($matricula->fresh()->horarios->pluck('id')->contains($horarioDos->id));
    }

    public function test_asignar_horario_de_curso_reemplaza_la_asignacion_previa_del_mismo_curso(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        $curso = Curso::factory()->hasAttached($grado)->create();
        $seccionUno = Horario::factory()->create(['curso_id' => $curso->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $seccionDos = Horario::factory()->create(['curso_id' => $curso->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));
        $this->service()->asignarHorarioDeCurso($matricula, $curso->id, $seccionUno->id);

        $matricula = $this->service()->asignarHorarioDeCurso($matricula, $curso->id, $seccionDos->id);

        $this->assertSame([$seccionDos->id], $matricula->fresh()->horarios->pluck('id')->all());
    }

    public function test_asignar_horario_de_curso_a_null_lo_deja_sin_asignar(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        $horario = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));
        $matricula = $this->service()->asignarHorarioDeCurso($matricula, $horario->curso_id, $horario->id);

        $matricula = $this->service()->asignarHorarioDeCurso($matricula, $horario->curso_id, null);

        $this->assertTrue($matricula->fresh()->horarios->isEmpty());
    }

    public function test_asignar_horario_de_curso_con_uno_ajeno_al_grado_lanza_excepcion(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        $horarioAjeno = Horario::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->expectException(ValidationException::class);

        $this->service()->asignarHorarioDeCurso($matricula, $horarioAjeno->curso_id, $horarioAjeno->id);
    }

    /**
     * La lógica de filtrado en sí (grado+ciclo, paralelos, etc.) ya está
     * probada a fondo en tests/Feature/Academico/FiltroMatriculaAcademicoTest.php
     * y tests/Feature/Reportes/ReporteServiceTest.php -- esto solo verifica
     * que listarEstudiantes() (la búsqueda avanzada, §25 del prompt
     * maestro) de verdad pasa los filtros hasta ahí.
     */
    public function test_listar_estudiantes_filtra_por_grado(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();

        $estudianteDelGrado = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudianteDelGrado->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $estudianteDeOtroGrado = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudianteDeOtroGrado->id]);

        $resultado = $this->service()->listarEstudiantes(null, null, gradoId: $grado->id);

        $this->assertCount(1, $resultado);
        $this->assertSame($estudianteDelGrado->id, $resultado->first()->id);
    }

    public function test_listar_estudiantes_filtra_por_docente(): void
    {
        $docente = User::factory()->create();
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $estudianteDelDocente = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudianteDelDocente->id, 'grado_id' => $horario->grado_id, 'ciclo_id' => $horario->ciclo_id]);

        $otroEstudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $otroEstudiante->id]);

        $resultado = $this->service()->listarEstudiantes(null, null, docenteId: $docente->id);

        $this->assertCount(1, $resultado);
        $this->assertSame($estudianteDelDocente->id, $resultado->first()->id);
    }
}
